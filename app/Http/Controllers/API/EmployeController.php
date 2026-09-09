<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use Illuminate\Http\Request;

class EmployeController extends Controller
{
    public function index(Request $request)
    {
        $demandeur = $request->user();
        $peutVoirDocuments = in_array($demandeur->role, ['admin', 'drh', 'directeur']);

        $query = Employe::with(array_filter([
            'user', 'departement', $peutVoirDocuments ? 'documents' : null,
        ]));

        if ($request->departement_id) {
            $query->where('departement_id', $request->departement_id);
        }
        if ($request->role) {
            $query->whereHas('user', fn($q) => $q->where('role', $request->role));
        }

        return response()->json($query->get());
    }

    public function show(Request $request, $id)
    {
        $demandeur = $request->user();
        $peutVoirDocuments = in_array($demandeur->role, ['admin', 'drh', 'directeur']);

        // Un employé/stagiaire ne peut consulter que son propre profil (avec ses propres documents)
        if (!$peutVoirDocuments && (!$demandeur->employe || $demandeur->employe->id != $id)) {
            return response()->json(['message' => "Vous n'êtes pas autorisé à consulter ce profil"], 403);
        }

        $employe = Employe::with(array_filter([
            'user', 'departement', 'documents',
        ]))->findOrFail($id);

        return response()->json($employe);
    }

    public function store(Request $request)
    {
        $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'poste'          => 'nullable|string',
        ]);

        $employe = Employe::create([
            'user_id'        => $request->user_id,
            'departement_id' => $request->departement_id,
            'poste'          => $request->poste,
        ]);

        return response()->json(['message' => 'Employé créé', 'employe' => $employe], 201);
    }
}