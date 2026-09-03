<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\OffreEmploi;
use Illuminate\Http\Request;
use App\Notifications\NouvelleCandidatureNotification;

class CandidatureController extends Controller
{
    public function index()
    {
        return response()->json(Candidature::with('offre', 'entretiens')->get());
    }

    // Candidature publique (sans auth) — un candidat externe postule
    public function store(Request $request)
    {
        $request->validate([
            'offre_id'           => 'required|exists:offres_emploi,id',
            'nom_candidat'       => 'required|string',
            'email_candidat'     => 'required|email',
            'telephone_candidat' => 'nullable|string',
            'lettre_motivation'  => 'nullable|string',
        ]);

        $offre = OffreEmploi::findOrFail($request->offre_id);
        if ($offre->statut !== 'ouverte') {
            return response()->json(['message' => "Cette offre n'est plus disponible."], 422);
        }

        $candidature = Candidature::create([
            ...$request->all(),
            'statut' => 'recue',
        ]);

        $candidature->load('offre');

    // Notifie Admin et DRH
        \App\Models\User::whereIn('role', ['admin', 'drh'])->each(function($user) use ($candidature) {
            $user->notify(new NouvelleCandidatureNotification($candidature));
        });


        return response()->json(['message' => 'Candidature envoyée', 'candidature' => $candidature], 201);
    }

    public function show($id)
    {
        return response()->json(Candidature::with('offre', 'entretiens.interviewer')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $candidature = Candidature::findOrFail($id);
        $candidature->update($request->only('statut'));
        return response()->json(['message' => 'Candidature mise à jour', 'candidature' => $candidature]);
    }

    public function destroy($id)
    {
        Candidature::findOrFail($id)->delete();
        return response()->json(['message' => 'Candidature supprimée']);
    }
}