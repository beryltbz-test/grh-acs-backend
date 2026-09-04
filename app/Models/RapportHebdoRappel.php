<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapportHebdoRappel extends Model
{
    protected $fillable = ['employe_id', 'annee', 'semaine', 'sous_type'];

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }
}