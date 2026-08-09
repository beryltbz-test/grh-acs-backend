<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrGlobal extends Model
{
    protected $table = 'qr_globals';

    protected $fillable = [
        'code', 'genere_par',
        'limite_arrivee_employe', 'limite_arrivee_stagiaire',
        'blocage_depart_debut', 'blocage_depart_fin',
        'latitude', 'longitude', 'rayon_metres',
    ];

    public function generateur()
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    // Renvoie le QR global actif (un seul en base, on prend le plus récent)
    public static function actuel()
    {
        return self::latest()->first();
    }
}