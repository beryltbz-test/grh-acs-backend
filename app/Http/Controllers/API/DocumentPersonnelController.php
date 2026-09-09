<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentEmploye;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentPersonnelController extends Controller
{
    const TYPES = ['document_personnel', 'rapport_hebdomadaire'];
    const MAX_FICHIERS = 2;

    // Liste des documents personnels de l'employé/stagiaire connecté (les deux types confondus)
    public function index(Request $request)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        return response()->json(
            $employe->documents()->whereIn('type', self::TYPES)->orderBy('created_at', 'desc')->get()
        );
    }

    // Upload d'un nouveau document personnel, ou du rapport hebdomadaire (Excel uniquement) — max 2 au total
    public function store(Request $request)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        $nombreActuel = $employe->documents()->whereIn('type', self::TYPES)->count();
        if ($nombreActuel >= self::MAX_FICHIERS) {
            return response()->json([
                'message' => "Vous avez déjà " . self::MAX_FICHIERS . " fichiers dans votre espace personnel. Supprimez-en un avant d'en ajouter un nouveau.",
            ], 422);
        }

        $estRapportHebdo = $request->boolean('est_rapport_hebdo');
        $type = $estRapportHebdo ? 'rapport_hebdomadaire' : 'document_personnel';

        $regleFormat = $estRapportHebdo ? 'mimes:xls,xlsx' : 'mimes:xls,xlsx,csv,doc,docx,pdf,ppt,pptx';
        $messageFormat = $estRapportHebdo
            ? "Le rapport hebdomadaire doit être un fichier Excel (.xls ou .xlsx) uniquement."
            : "Format non autorisé. Formats acceptés : Excel (.xls, .xlsx, .csv), Word (.doc, .docx), PDF (.pdf), PowerPoint (.ppt, .pptx).";

        $request->validate([
            'fichier' => "required|file|{$regleFormat}|max:15360",
        ], [
            'fichier.max' => "Ce fichier dépasse la taille maximale autorisée (15 Mo). Réduisez la taille du fichier avant de réessayer.",
            'fichier.mimes' => $messageFormat,
            'fichier.required' => "Merci de sélectionner un fichier.",
        ]);

        $path = $request->file('fichier')->store('documents/personnels', 'public');

        $document = DocumentEmploye::create([
            'employe_id'     => $employe->id,
            'type'           => $type,
            'nom_fichier'    => $request->file('fichier')->getClientOriginalName(),
            'chemin_fichier' => $path,
        ]);

        return response()->json([
            'message'  => 'Fichier envoyé avec succès',
            'document' => $document,
        ], 201);
    }

    // Suppression d'un document personnel (uniquement le sien, les deux types confondus)
    public function destroy(Request $request, $id)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        $document = DocumentEmploye::where('employe_id', $employe->id)
            ->whereIn('type', self::TYPES)
            ->where('id', $id)
            ->first();

        if (!$document) {
            return response()->json(['message' => 'Document introuvable'], 404);
        }

        Storage::disk('public')->delete($document->chemin_fichier);
        $document->delete();

        return response()->json(['message' => 'Fichier supprimé']);
    }
}