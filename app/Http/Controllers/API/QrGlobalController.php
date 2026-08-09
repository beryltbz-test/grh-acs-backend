<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QrGlobal;
use Illuminate\Http\Request;

class QrGlobalController extends Controller
{
    // Récupère le QR global actuel (le génère avec des règles par défaut s'il n'existe pas encore)
    public function show(Request $request)
    {
        $qr = QrGlobal::actuel();

        if (!$qr) {
            $qr = QrGlobal::create([
                'code' => 'GLOBAL-' . strtoupper(bin2hex(random_bytes(8))),
                'genere_par' => $request->user()->id,
                'limite_arrivee_employe' => '08:30:00',
                'limite_arrivee_stagiaire' => '08:00:00',
                'blocage_depart_debut' => '09:00:00',
                'blocage_depart_fin' => '18:30:00',
                'latitude' => 6.381872,
                'longitude' => 2.413610,
                'rayon_metres' => 30,
            ]);
        }

        return response()->json($qr);
    }

    // Régénère un nouveau QR global avec ses propres règles (l'ancien devient inutilisable)
    public function regenerer(Request $request)
    {
        $request->validate([
            'limite_arrivee_employe'   => 'required|date_format:H:i',
            'limite_arrivee_stagiaire' => 'required|date_format:H:i',
            'blocage_depart_debut'     => 'required|date_format:H:i',
            'blocage_depart_fin'       => 'required|date_format:H:i',
            'latitude'                 => 'required|numeric|between:-90,90',
            'longitude'                => 'required|numeric|between:-180,180',
            'rayon_metres'             => 'required|integer|min:5|max:1000',
        ]);

        $qr = QrGlobal::create([
            'code' => 'GLOBAL-' . strtoupper(bin2hex(random_bytes(8))),
            'genere_par' => $request->user()->id,
            'limite_arrivee_employe' => $request->limite_arrivee_employe,
            'limite_arrivee_stagiaire' => $request->limite_arrivee_stagiaire,
            'blocage_depart_debut' => $request->blocage_depart_debut,
            'blocage_depart_fin' => $request->blocage_depart_fin,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'rayon_metres' => $request->rayon_metres,
        ]);

        return response()->json(['message' => 'QR Code global régénéré', 'qr' => $qr], 201);
    }
}