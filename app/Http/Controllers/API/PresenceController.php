<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Presence;
use App\Models\Employe;
use App\Models\QrGlobal;
use App\Models\DemandeAbsence;
use App\Models\DocumentEmploye;
use App\Models\RapportHebdoRappel;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PresenceController extends Controller
{
    private function distanceMetres($lat1, $lng1, $lat2, $lng2)
    {
        $rayonTerre = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $rayonTerre * $c;
    }

    private function limiteArriveePour($qrGlobalActuel, $employe)
    {
        if (!$qrGlobalActuel) {
            return null;
        }

        return $employe->user->role === 'stagiaire'
            ? $qrGlobalActuel->limite_arrivee_stagiaire
            : $qrGlobalActuel->limite_arrivee_employe;
    }

    private function aSoumisRapportHebdo(Employe $employe, Carbon $semaineRef)
    {
        $debut = $semaineRef->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fin = $semaineRef->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        return DocumentEmploye::where('employe_id', $employe->id)
            ->where('type', 'document_personnel')
            ->whereBetween('created_at', [$debut, $fin])
            ->exists();
    }

    private function verifierRappelHebdo(Employe $employe, string $sousType, Carbon $maintenant)
    {
        if ($employe->user->role !== 'employe') {
            return;
        }

        $semaineRef = $sousType === 'ultimatum_lundi' ? $maintenant->copy()->subWeek() : $maintenant->copy();

        if ($this->aSoumisRapportHebdo($employe, $semaineRef)) {
            return;
        }

        $creation = RapportHebdoRappel::firstOrCreate([
            'employe_id' => $employe->id,
            'annee'      => $semaineRef->isoWeekYear,
            'semaine'    => $semaineRef->isoWeek,
            'sous_type'  => $sousType,
        ]);

        if (!$creation->wasRecentlyCreated) {
            return;
        }

        $heureLimite = $sousType === 'ultimatum_lundi' ? config('rapports.heure_limite_lundi') : null;
        $employe->user->notify(new \App\Notifications\RapportHebdoRappelNotification($sousType, $heureLimite));
    }

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
            $employe = $request->user()->employe()->with('user')->first();
            if (!$employe) {
                return response()->json(['message' => 'Aucun profil employé associé à ce compte'], 404);
            }

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
            $employe = Employe::with('user')->where('qr_token', $request->qr_token)->first();
            if (!$employe) {
                return response()->json(['message' => 'QR Code invalide'], 404);
            }
        }

        if ($employe->user->statut !== 'actif') {
            return response()->json(['message' => 'Ce compte est désactivé, le pointage est bloqué.'], 403);
        }

        $maintenant = Carbon::now();
        $today = $maintenant->toDateString();
        $now = $maintenant->toTimeString();

        $presence = Presence::where('employe_id', $employe->id)
            ->where('date', $today)
            ->first();

        if (!$presence) {
            $limite = $this->limiteArriveePour($qrGlobalActuel, $employe);
            $enRetard = $limite ? ($now > $limite) : false;

            $presence = Presence::create([
                'employe_id'    => $employe->id,
                'date'          => $today,
                'heure_arrivee' => $now,
                'en_retard'     => $enRetard,
                'scanne_par'    => auth()->id(),
            ]);

            if ($enRetard) {
                $employe->user->notify(new \App\Notifications\QrGlobalHorsDelaiNotification(substr($limite, 0, 5), substr($now, 0, 5)));
            } else {
                $employe->user->notify(new \App\Notifications\PresenceScanneeNotification('arrivee', $now));
            }

            if ($maintenant->isFriday()) {
                $this->verifierRappelHebdo($employe, 'rappel_arrivee_vendredi', $maintenant);
            } elseif ($maintenant->isMonday()) {
                $this->verifierRappelHebdo($employe, 'ultimatum_lundi', $maintenant);
            }

            return response()->json([
                'message'   => $enRetard ? 'Arrivée enregistrée (en retard)' : 'Arrivée enregistrée',
                'type'      => 'arrivee',
                'en_retard' => $enRetard,
                'employe'   => $employe->user->name,
                'heure'     => $now,
                'presence'  => $presence,
            ]);
        }

        if (!$presence->heure_depart) {
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

            if ($maintenant->isFriday()) {
                $this->verifierRappelHebdo($employe, 'rappel_depart_vendredi', $maintenant);
            }

            return response()->json([
                'message' => 'Départ enregistré',
                'type'    => 'depart',
                'employe' => $employe->user->name,
                'heure'   => $now,
                'presence' => $presence,
            ]);
        }

        return response()->json([
            'message' => 'Cet employé a déjà pointé son arrivée et son départ aujourd\'hui',
            'presence' => $presence,
        ], 409);
    }

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

    public function monQrCode(Request $request)
    {
        $employe = $request->user()->employe;

        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        return response()->json(['qr_token' => $employe->qr_token]);
    }

    // Historique paginé — la table présences grandit indéfiniment (aucune purge),
    // charger tout en mémoire sans pagination devient rapidement problématique.
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

        $parPage = min((int) ($request->per_page ?? 100), 500);

        return response()->json(
            $query->orderBy('date', 'desc')->orderBy('heure_arrivee', 'desc')->paginate($parPage)
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

    public function liste(Request $request)
    {
        $date = $request->date ?: Carbon::today()->toDateString();
        $estAujourdhui = $date === Carbon::today()->toDateString();
        $statutFiltre = $request->statut;

        $qrGlobalActuel = QrGlobal::actuel();
        $now = Carbon::now()->toTimeString();
        $finBlocage = $qrGlobalActuel->blocage_depart_fin ?? '18:30:00';

        $employes = Employe::with('user', 'departement')
            ->whereHas('user', function ($q) {
                $q->where('statut', 'actif')->whereIn('role', ['employe', 'stagiaire']);
            })
            ->get();

        $presencesDuJour = Presence::where('date', $date)->get()->keyBy('employe_id');

        $roster = $employes->map(function ($employe) use ($presencesDuJour, $qrGlobalActuel, $estAujourdhui, $now, $finBlocage, $date) {
            $presence = $presencesDuJour->get($employe->id);
            $limite = $this->limiteArriveePour($qrGlobalActuel, $employe);

            if ($presence && $presence->heure_arrivee) {
                $etat = $presence->en_retard ? 'en_retard' : 'a_l_heure';
            } else {
                $cloture = $estAujourdhui ? ($now >= $finBlocage) : true;
                if (!$estAujourdhui) {
                    $etat = 'absent';
                } elseif ($cloture) {
                    $etat = 'absent';
                } elseif ($limite && $now > $limite) {
                    $etat = 'en_retard';
                } else {
                    $etat = 'en_attente';
                }
            }

            return [
                'employe_id'    => $employe->id,
                'nom'           => $employe->user->name,
                'role'          => $employe->user->role,
                'departement'   => $employe->departement->nom ?? null,
                'date'          => $date,
                'heure_arrivee' => $presence->heure_arrivee ?? null,
                'heure_depart'  => $presence->heure_depart ?? null,
                'etat'          => $etat,
            ];
        });

        if ($statutFiltre && $statutFiltre !== 'tous') {
            $roster = $roster->where('etat', $statutFiltre)->values();
        }

        return response()->json([
            'date'  => $date,
            'total' => $roster->count(),
            'compteurs' => [
                'en_attente' => $employes->count() ? $roster->countBy('etat')->get('en_attente', 0) : 0,
                'a_l_heure'  => $roster->countBy('etat')->get('a_l_heure', 0),
                'en_retard'  => $roster->countBy('etat')->get('en_retard', 0),
                'absent'     => $roster->countBy('etat')->get('absent', 0),
            ],
            'data' => $roster->values(),
        ]);
    }
}