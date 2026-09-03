<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentEmploye;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentPersonnelController extends Controller
{
    const TYPE = 'document_personnel';
    const MAX_FICHIERS = 2;

    // Liste des documents personnels de l'employé/stagiaire connecté
    public function index(Request $request)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        return response()->json(
            $employe->documents()->where('type', self::TYPE)->orderBy('created_at', 'desc')->get()
        );
    }

    // Upload d'un nouveau document personnel (max 2 par compte)
    public function store(Request $request)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        $nombreActuel = $employe->documents()->where('type', self::TYPE)->count();
        if ($nombreActuel >= self::MAX_FICHIERS) {
            return response()->json([
                'message' => "Vous avez déjà " . self::MAX_FICHIERS . " fichiers dans votre espace personnel. Supprimez-en un avant d'en ajouter un nouveau.",
            ], 422);
        }

        $request->validate([
            'fichier' => 'required|file|mimes:xls,xlsx,csv,doc,docx,pdf,ppt,pptx|max:15360',
        ], [
            'fichier.max' => "Ce fichier dépasse la taille maximale autorisée (15 Mo). Réduisez la taille du fichier avant de réessayer.",
            'fichier.mimes' => "Format non autorisé. Formats acceptés : Excel (.xls, .xlsx, .csv), Word (.doc, .docx), PDF (.pdf), PowerPoint (.ppt, .pptx).",
            'fichier.required' => "Merci de sélectionner un fichier.",
        ]);

        $path = $request->file('fichier')->store('documents/personnels', 'public');

        $document = DocumentEmploye::create([
            'employe_id'     => $employe->id,
            'type'           => self::TYPE,
            'nom_fichier'    => $request->file('fichier')->getClientOriginalName(),
            'chemin_fichier' => $path,
        ]);

        return response()->json([
            'message'  => 'Fichier envoyé avec succès',
            'document' => $document,
        ], 201);
    }

    // Suppression d'un document personnel (uniquement le sien)
    public function destroy(Request $request, $id)
    {
        $employe = $request->user()->employe;
        if (!$employe) {
            return response()->json(['message' => 'Aucun profil employé associé'], 404);
        }

        $document = DocumentEmploye::where('employe_id', $employe->id)
            ->where('type', self::TYPE)
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