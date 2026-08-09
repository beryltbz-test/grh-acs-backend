<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\User;
use App\Models\DocumentEmploye;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employe::with('user', 'departement', 'documents');

        if ($request->departement_id) {
            $query->where('departement_id', $request->departement_id);
        }

        if ($request->role) {
            $query->whereHas('user', fn($q) => $q->where('role', $request->role));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'prenom'           => 'required|string',
            'nom'              => 'required|string',
            'email'            => 'nullable|email|unique:users',
            'password'         => 'nullable|min:6',
            'role'             => 'required|in:admin,drh,directeur,employe,stagiaire',
            'telephone'        => 'nullable|string',
            'date_naissance'   => 'nullable|date',
            'adresse'          => 'nullable|string',
            'departement_id'   => 'required|exists:departements,id',
            'poste'            => 'nullable|string',
            'type_contrat'     => 'nullable|in:CDI,CDD,Stage,Consultant',
            'date_embauche'    => 'nullable|date',
            'date_fin_contrat' => 'nullable|date',
            'salaire'          => 'nullable|numeric',
            'jours_conge_annuels' => 'nullable|integer',
            'notes'            => 'nullable|string',
            'cv'               => 'nullable|file|mimes:pdf|max:5120',
            'contrat'          => 'nullable|file|mimes:pdf|max:5120',
        ]);

        if (in_array($request->user()->role, ['drh', 'directeur']) && !in_array($request->role, ['employe', 'stagiaire'])) {
            return response()->json(['message' => "Vous ne pouvez créer que des employés ou des stagiaires"], 403);
        }

        $identifiantsDefinis = $request->filled('email') && $request->filled('password');

        // Si l'email/mot de passe ne sont pas fournis (création par DRH/Directeur),
        // on génère des valeurs temporaires ; l'Admin les redéfinira ensuite depuis Utilisateurs.jsx
        $email = $request->filled('email') ? $request->email : 'temp.' . uniqid() . '@acs.local';
        $password = $request->filled('password') ? $request->password : bin2hex(random_bytes(8));

        $user = User::create([
            'name'     => $request->prenom . ' ' . $request->nom,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => $request->role,
            'telephone'=> $request->telephone,
        ]);

        $matricule = 'ACS-' . strtoupper(substr($request->role, 0, 3)) . '-' . str_pad($user->id, 3, '0', STR_PAD_LEFT);

        $employe = Employe::create([
            'user_id'             => $user->id,
            'departement_id'      => $request->departement_id,
            'matricule'           => $matricule,
            'poste'               => $request->poste,
            'type_contrat'        => $request->type_contrat,
            'date_embauche'       => $request->date_embauche,
            'date_fin_contrat'    => $request->date_fin_contrat,
            'date_naissance'      => $request->date_naissance,
            'adresse'             => $request->adresse,
            'salaire'             => $request->salaire,
            'jours_conge_annuels' => $request->jours_conge_annuels,
            'notes'               => $request->notes,
        ]);

        if ($request->hasFile('cv')) {
            $path = $request->file('cv')->store('documents/cv', 'public');
            DocumentEmploye::create([
                'employe_id'     => $employe->id,
                'type'           => 'cv',
                'nom_fichier'    => $request->file('cv')->getClientOriginalName(),
                'chemin_fichier' => $path,
            ]);
        }

        if ($request->hasFile('contrat')) {
            $path = $request->file('contrat')->store('documents/contrats', 'public');
            DocumentEmploye::create([
                'employe_id'     => $employe->id,
                'type'           => 'contrat',
                'nom_fichier'    => $request->file('contrat')->getClientOriginalName(),
                'chemin_fichier' => $path,
            ]);
        }

        // Rappel à tous les admins : ne pas oublier d'accorder l'accès scanner si nécessaire
        \App\Models\User::where('role', 'admin')->each(function ($admin) use ($user) {
            $admin->notify(new \App\Notifications\RappelAccorderScannerNotification($user->name));
        });

        return response()->json([
            'message' => $identifiantsDefinis
                ? 'Employé créé avec succès'
                : "Employé créé avec succès. Un administrateur doit encore définir son email et son mot de passe de connexion.",
            'identifiants_a_definir' => !$identifiantsDefinis,
            'employe' => $employe->load('user', 'departement', 'documents')
        ], 201);
    }

    public function show($id)
    {
        return response()->json(
            Employe::with('user', 'departement', 'documents', 'demandesAbsence', 'presences')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $employe = Employe::findOrFail($id);
        $ancienRole = $employe->user->role;
        $demandeur = $request->user();

        if (in_array($demandeur->role, ['drh', 'directeur']) && !in_array($ancienRole, ['employe', 'stagiaire'])) {
            return response()->json(['message' => "Vous ne pouvez modifier que des employés ou des stagiaires"], 403);
        }

        $employe->update($request->only(
            'departement_id', 'poste', 'type_contrat', 'date_embauche',
            'date_fin_contrat', 'date_naissance', 'adresse', 'salaire',
            'jours_conge_annuels', 'notes'
        ));

        if ($request->has('name') || $request->has('email') || $request->has('telephone')) {
            $employe->user->update($request->only('name', 'email', 'telephone'));
        }

        if ($request->hasFile('cv')) {
            $path = $request->file('cv')->store('documents/cv', 'public');
            DocumentEmploye::create([
                'employe_id'     => $employe->id,
                'type'           => 'cv',
                'nom_fichier'    => $request->file('cv')->getClientOriginalName(),
                'chemin_fichier' => $path,
            ]);
        }

        if ($request->hasFile('contrat')) {
            $path = $request->file('contrat')->store('documents/contrats', 'public');
            DocumentEmploye::create([
                'employe_id'     => $employe->id,
                'type'           => 'contrat',
                'nom_fichier'    => $request->file('contrat')->getClientOriginalName(),
                'chemin_fichier' => $path,
            ]);
        }

        return response()->json([
            'message' => 'Employé modifié',
            'employe' => $employe->load('user', 'departement', 'documents')
        ]);
    }

    public function destroy($id)
    {
        $employe = Employe::findOrFail($id);
        $employe->user->delete();
        return response()->json(['message' => 'Employé supprimé']);
    }
}