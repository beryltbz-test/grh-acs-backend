<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Employe;
use App\Models\QrGlobal;
use App\Models\DemandeAbsence;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PresenceController extends Controller
{
    // Distance en mètres entre deux points GPS (formule de Haversine)
    private function distanceMetres($lat1, $lng1, $lat2, $lng2)
    {
        $rayonTerre = 6371000; // mètres
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $rayonTerre * $c;
    }

    // Scan du QR — pointe arrivée ou départ selon l'état du jour
    // Gère à la fois le QR individuel (opérateur DRH) et le QR global (auto-scan par l'employé)
    public function scanner(Request $request)
    {
        $request->validate([
            'qr_token'  => 'required|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $qrGlobalActuel = QrGlobal::actuel();
        $estScanGlobal = $qrGlobalActuel && $request->qr_token === $qrGlobalActuel->code;

        if ($estScanGlobal) {
            // QR global : la personne scanne elle-même, identifiée par son propre compte
            $employe = $request->user()->employe()->with('user')->first();
            if (!$employe) {
                return response()->json(['message' => 'Aucun profil employé associé à ce compte'], 404);
            }

            // Vérification de la géolocalisation : uniquement pour le QR global (auto-scan)
            if (!$request->filled('latitude') || !$request->filled('longitude')) {
                return response()->json([
                    'message' => "La localisation est requise pour utiliser le QR Code général. Merci d'autoriser l'accès à votre position.",
                ], 422);
            }

            $distance = $this->distanceMetres(
                $request->latitude, $request->longitude,
                $qrGlobalActuel->latitude, $qrGlobalActuel->longitude
            );

            if ($distance > $qrGlobalActuel->rayon_metres) {
                return response()->json([
                    'message' => "Vous devez être présent dans les locaux pour pointer via le QR Code général.",
                ], 422);
            }
        } else {
            // QR individuel : identifie la cible par le token scanné
            $employe = Employe::with('user')->where('qr_token', $request->qr_token)->first();
            if (!$employe) {
                return response()->json(['message' => 'QR Code invalide'], 404);
            }
        }

        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        $presence = Presence::where('employe_id', $employe->id)
            ->where('date', $today)
            ->first();

        if (!$presence) {
            // Premier scan du jour → Arrivée

            if ($estScanGlobal) {
                $limite = $employe->user->role === 'stagiaire'
                    ? $qrGlobalActuel->limite_arrivee_stagiaire
                    : $qrGlobalActuel->limite_arrivee_employe;

                if ($now > $limite) {
                    $employe->user->notify(new \App\Notifications\QrGlobalHorsDelaiNotification(substr($limite, 0, 5)));
                    return response()->json([
                        'message' => "Heure limite dépassée (" . substr($limite, 0, 5) . "). Merci de vous enregistrer auprès de la DRH avec votre QR Code personnel.",
                    ], 422);
                }
            }

            $presence = Presence::create([
                'employe_id'    => $employe->id,
                'date'          => $today,
                'heure_arrivee' => $now,
                'scanne_par'    => auth()->id(),
            ]);

            $employe->user->notify(new \App\Notifications\PresenceScanneeNotification('arrivee', $now));

            return response()->json([
                'message' => 'Arrivée enregistrée',
                'type'    => 'arrivee',
                'employe' => $employe->user->name,
                'heure'   => $now,
                'presence' => $presence,
            ]);
        }

        if (!$presence->heure_depart) {
            // Deuxième scan du jour → Départ

            if ($estScanGlobal) {
                $debutBlocage = $qrGlobalActuel->blocage_depart_debut;
                $finBlocage = $qrGlobalActuel->blocage_depart_fin;

                if ($now >= $debutBlocage && $now < $finBlocage) {
                    $permissionValide = DemandeAbsence::where('employe_id', $employe->id)
                        ->where('type', 'permission')
                        ->where('statut', 'approuvee')
                        ->where('date_debut', $today)
                        ->whereNotNull('heure_debut')
                        ->whereNotNull('heure_fin')
                        ->where('heure_debut', '<=', $now)
                        ->where('heure_fin', '>=', $now)
                        ->exists();

                    if (!$permissionValide) {
                        return response()->json([
                            'message' => "Le pointage de départ via le QR Code général n'est pas disponible entre " . substr($debutBlocage, 0, 5) . " et " . substr($finBlocage, 0, 5) . ". Revenez plus tard, ou présentez-vous avec une permission validée en cours.",
                        ], 422);
                    }
                }
            }

            $presence->update([
                'heure_depart' => $now,
                'scanne_par'   => auth()->id(),
            ]);

            $employe->user->notify(new \App\Notifications\PresenceScanneeNotification('depart', $now));

            return response()->json([
                'message' => 'Départ enregistré',
                'type'    => 'depart',
                'employe' => $employe->user->name,
                'heure'   => $now,
                'presence' => $presence,
            ]);
        }

        // Déjà pointé arrivée + départ aujourd'hui
        return response()->json([
            'message' => 'Cet employé a déjà pointé son arrivée et son départ aujourd\'hui',
            'presence' => $presence,
        ], 409);
    }

    // Liste des présences (filtrable par employé/période)
    public function index(Request $request)
    {
        $query = Presence::with('employe.user', 'employe.departement', 'scannePar');

        if ($request->employe_id) {
            $query->where('employe_id', $request->employe_id);
        }
        if ($request->date_debut && $request->date_fin) {
            $query->whereBetween('date', [$request->date_debut, $request->date_fin]);
        }

        return response()->json($query->orderBy('date', 'desc')->get());
    }

    // QR Token de l'employé connecté (pour affichage dans son espace)
    public function monQrCode(Request $request)
    {
        $employe = $request->user()->employe;

        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        return response()->json(['qr_token' => $employe->qr_token]);
    }

    public function historique(Request $request)
    {
        $query = Presence::with('employe.user', 'employe.departement', 'scannePar');

        if ($request->date) {
            $query->where('date', $request->date);
        } elseif ($request->mois && $request->annee) {
            $query->whereYear('date', $request->annee)
                ->whereMonth('date', $request->mois);
        } elseif ($request->annee) {
            $query->whereYear('date', $request->annee);
        }

        if ($request->employe_id) {
            $query->where('employe_id', $request->employe_id);
        }

        return response()->json(
            $query->orderBy('date', 'desc')->orderBy('heure_arrivee', 'desc')->get()
        );
    }

    public function presentsAujourdhui()
    {
        $today = \Carbon\Carbon::today()->toDateString();

        $presents = Presence::with('employe.user', 'employe.departement')
            ->where('date', $today)
            ->whereNotNull('heure_arrivee')
            ->get();

        return response()->json($presents);
    }
}