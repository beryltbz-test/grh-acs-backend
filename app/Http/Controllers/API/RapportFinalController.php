<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentEmploye;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RapportFinalController extends Controller
{
    // Upload (ou remplacement) du rapport final de stage — réservé aux Stagiaires
    public function store(Request $request)
    {
        if ($request->user()->role !== 'stagiaire') {
            return response()->json(['message' => "Seul un stagiaire peut déposer un rapport final"], 403);
        }

        $request->validate([
            'rapport_final' => 'required|file|mimes:pdf|max:10240', // 10 Mo max
        ]);

        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé à ce compte'], 404);
        }

        // Remplace l'éventuel rapport final déjà déposé (un seul actif à la fois)
        $ancien = DocumentEmploye::where('employe_id', $employe->id)
            ->where('type', 'rapport_final')
            ->first();

        if ($ancien) {
            Storage::disk('public')->delete($ancien->chemin_fichier);
            $ancien->delete();
        }

        $path = $request->file('rapport_final')->store('documents/rapports-finaux', 'public');

        $document = DocumentEmploye::create([
            'employe_id'     => $employe->id,
            'type'           => 'rapport_final',
            'nom_fichier'    => $request->file('rapport_final')->getClientOriginalName(),
            'chemin_fichier' => $path,
        ]);

        return response()->json([
            'message'  => 'Rapport final déposé avec succès',
            'document' => $document,
        ], 201);
    }

    // Consulter son propre rapport final déposé (pour affichage côté stagiaire)
    public function monRapportFinal(Request $request)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        $document = DocumentEmploye::where('employe_id', $employe->id)
            ->where('type', 'rapport_final')
            ->first();

        return response()->json($document);
    }
}