<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_globals', function (Blueprint $table) {
            $table->time('limite_arrivee_employe')->default('08:30:00')->after('code');
            $table->time('limite_arrivee_stagiaire')->default('08:00:00')->after('limite_arrivee_employe');
            $table->time('blocage_depart_debut')->default('09:00:00')->after('limite_arrivee_stagiaire');
            $table->time('blocage_depart_fin')->default('18:30:00')->after('blocage_depart_debut');
            $table->decimal('latitude', 10, 7)->nullable()->after('blocage_depart_fin');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('rayon_metres')->default(30)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('qr_globals', function (Blueprint $table) {
            $table->dropColumn([
                'limite_arrivee_employe', 'limite_arrivee_stagiaire',
                'blocage_depart_debut', 'blocage_depart_fin',
                'latitude', 'longitude', 'rayon_metres',
            ]);
        });
    }
};