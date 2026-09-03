<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OffreEmploi;
use Illuminate\Http\Request;
use App\Notifications\OffrePublieeNotification;

class OffreEmploiController extends Controller
{
    public function index()
    {
        return response()->json(OffreEmploi::with('departement', 'publiePar', 'candidatures')->get());
    }

    // Endpoint public (page de candidature) : uniquement les offres ouvertes,
    // sans exposer les candidatures (données personnelles des candidats).
    public function publiques()
    {
        return response()->json(
            OffreEmploi::with('departement')
                ->where('statut', 'ouverte')
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'titre'          => 'required|string',
            'departement_id' => 'required|exists:departements,id',
            'description'    => 'required|string',
            'type_contrat'   => 'required|in:CDI,CDD,Stage,Consultant',
            'date_publication' => 'required|date',
            'date_limite'    => 'nullable|date',
        ]);

        $offre = OffreEmploi::create([
            ...$request->all(),
            'publiee_par' => auth()->id(),
            'statut'      => 'ouverte',
        ]);
        
        \App\Models\User::whereIn('role', ['employe', 'stagiaire'])->each(function($user) use ($offre) {
            $user->notify(new OffrePublieeNotification($offre));
        });

        return response()->json(['message' => 'Offre créée', 'offre' => $offre], 201);
    }

    public function show($id)
    {
        return response()->json(
            OffreEmploi::with('departement', 'publiePar', 'candidatures.entretiens')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $offre = OffreEmploi::findOrFail($id);
        $offre->update($request->all());
        return response()->json(['message' => 'Offre modifiée', 'offre' => $offre]);
    }

    public function destroy($id)
    {
        OffreEmploi::findOrFail($id)->delete();
        return response()->json(['message' => 'Offre supprimée']);
    }
}