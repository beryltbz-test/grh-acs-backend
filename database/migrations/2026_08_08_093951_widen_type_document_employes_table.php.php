<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la colonne "type" est un ENUM restreint (cv, contrat, ...), cette migration
        // la convertit en chaîne libre pour accepter "rapport_final" sans erreur.
        // Si "type" est déjà une string/varchar, cette migration est sans effet (safe no-op).
        Schema::table('document_employes', function (Blueprint $table) {
            $table->string('type', 50)->change();
        });
    }

    public function down(): void
    {
        // Pas de rollback automatique nécessaire (changement non destructif)
    }
};