<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapport_hebdo_rappels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employe_id')->constrained('employes')->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedTinyInteger('semaine');
            $table->string('sous_type', 40); // rappel_arrivee_vendredi | rappel_midi_vendredi | rappel_depart_vendredi | ultimatum_lundi | notification_drh
            $table->timestamps();

            $table->unique(['employe_id', 'annee', 'semaine', 'sous_type'], 'rappel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapport_hebdo_rappels');
    }
};