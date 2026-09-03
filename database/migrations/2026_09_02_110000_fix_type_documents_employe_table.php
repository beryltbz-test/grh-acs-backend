<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corrige la vraie table utilisée par le modèle DocumentEmploye (documents_employe).
        // La migration précédente (2026_08_08_999999) ciblait par erreur "document_employes"
        // (table orpheline, jamais utilisée par le code), ce qui laissait "type" en ENUM strict
        // ('cv','diplome','contrat','piece_identite','autre') sur la table réellement utilisée.
        Schema::table('documents_employe', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        // Pas de rollback automatique (changement non destructif, aucune donnée perdue)
    }
};