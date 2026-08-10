<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_employes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('nom_fichier');
            $table->string('chemin_fichier');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_employes');
    }
};