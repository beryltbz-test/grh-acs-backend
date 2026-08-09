<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\DepartementController;
use App\Http\Controllers\API\EmployeController;
use App\Http\Controllers\API\DemandeAbsenceController;
use App\Http\Controllers\API\OffreEmploiController;
use App\Http\Controllers\API\CandidatureController;
use App\Http\Controllers\API\PresenceController;
use App\Http\Controllers\API\TravailStagiaireController;
use App\Http\Controllers\API\EntretienController;
use App\Http\Controllers\API\UserManagementController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\QrGlobalController;
use App\Http\Controllers\API\PinController;
use App\Http\Controllers\API\RapportFinalController;

// ============ Routes publiques ============
Route::post('/login', [AuthController::class, 'login']);

// Candidature externe (un candidat postule sans compte)
Route::post('/candidatures', [CandidatureController::class, 'store']);
Route::get('/offres-emploi/publiques', [OffreEmploiController::class, 'index']);

// ============ Routes protégées ============
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Notifications
    Route::get('notifications', function (Request $request) {
        return response()->json($request->user()->notifications);
    });
    Route::post('notifications/lire-tout', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['message' => 'Toutes les notifications lues']);
    });
    Route::post('notifications/{id}/lire', function (Request $request, $id) {
        $request->user()->notifications()->findOrFail($id)->markAsRead();
        return response()->json(['message' => 'Notification lue']);
    });

    // ============ Admin uniquement (gestion technique) ============
Route::middleware('role:admin')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('departements', DepartementController::class)->except(['index', 'show']);
    Route::get('utilisateurs', [UserManagementController::class, 'index']);
    Route::post('utilisateurs', [UserManagementController::class, 'store']);
    Route::delete('utilisateurs/{id}', [UserManagementController::class, 'destroy']);
    Route::get('permissions/{userId}', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::delete('permissions', [PermissionController::class, 'destroy']);
    Route::get('qr-global', [QrGlobalController::class, 'show']);
    Route::post('qr-global/regenerer', [QrGlobalController::class, 'regenerer']);
});
// Employés, absences, recrutement : Admin + DRH + Directeur // DRH/Directeur doivent désormais avoir la permission explicite
Route::middleware('permission:absences,admin')->group(function () {
    Route::post('demandes/{id}/traiter', [DemandeAbsenceController::class, 'traiter']);
});

// Scanner + Rapport présence : Admin + DRH UNIQUEMENT (pas Directeur)
// Scanner — accessible par rôle standard OU permission individuelle
Route::middleware('permission:scanner,admin')->group(function () {
    Route::post('presences/scanner', [PresenceController::class, 'scanner']);
    Route::get('presences/presents-aujourdhui', [PresenceController::class, 'presentsAujourdhui']);
    Route::post('presences/scanner-global', [PresenceController::class, 'scanner']);
});

// Historique présence — accessible par rôle standard OU permission individuelle
Route::middleware('permission:historique_presence,admin')->group(function () {
    Route::get('presences/historique', [PresenceController::class, 'historique']);
});

// ============ Admin + DRH + Directeur (lecture départements) ============
Route::middleware('permission:recrutement,admin')->group(function () {
    Route::get('departements', [DepartementController::class, 'index']);
    Route::get('departements/{id}', [DepartementController::class, 'show']);
    Route::apiResource('offres-emploi', OffreEmploiController::class)->except(['index']);
    Route::apiResource('candidatures', CandidatureController::class)->except(['store']);
    Route::post('entretiens', [EntretienController::class, 'store']);
    Route::put('entretiens/{id}', [EntretienController::class, 'update']);
});

Route::middleware('role:admin,drh,directeur')->group(function () {
    Route::post('employes', [EmployeController::class, 'store']);
    Route::put('utilisateurs/{id}', [UserManagementController::class, 'update']);
});

// ============ Toutes les routes communes ============
Route::middleware('role:admin,drh,directeur,employe,stagiaire')->group(function () {
    Route::get('employes', [EmployeController::class, 'index']);
    Route::get('employes/{id}', [EmployeController::class, 'show']);
    Route::apiResource('demandes', DemandeAbsenceController::class)->except(['destroy']);
    Route::get('offres-emploi', [OffreEmploiController::class, 'index']);
    Route::get('offres-emploi/{id}', [OffreEmploiController::class, 'show']);
    Route::get('presences/mon-qrcode', [PresenceController::class, 'monQrCode']);
    Route::get('pin/statut', [PinController::class, 'statut']);
    Route::post('pin/definir', [PinController::class, 'definir']);
    Route::post('pin/verifier', [PinController::class, 'verifier']);
    Route::get('travaux-stagiaire', [TravailStagiaireController::class, 'index']);
    Route::post('travaux-stagiaire', [TravailStagiaireController::class, 'store'] );
    Route::post('travaux-stagiaire/rapport-final', [RapportFinalController::class, 'store']);
    Route::get('travaux-stagiaire/mon-rapport-final', [RapportFinalController::class, 'monRapportFinal']);
    Route::get('mes-permissions', [PermissionController::class, 'mesPermissions']);
    Route::get('travaux-stagiaire/aujourd-hui', [TravailStagiaireController::class, 'monRapportDuJour']);
    Route::get('presences/mes-presences', function (Request $request) {
    $employe = $request->user()->employe;
        if (!$employe) return response()->json([]);
        return response()->json(
            \App\Models\Presence::where('employe_id', $employe->id)
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get()
        );
    });
});
});