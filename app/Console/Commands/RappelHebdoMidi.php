<?php

namespace App\Console\Commands;

use App\Models\Employe;
use App\Models\DocumentEmploye;
use App\Models\RapportHebdoRappel;
use App\Notifications\RapportHebdoRappelNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RappelHebdoMidi extends Command
{
    protected $signature = 'rapports:rappel-midi-vendredi';
    protected $description = "Envoie le rappel de milieu de journée (vendredi) aux employés n'ayant pas encore soumis leur rapport hebdomadaire";

    public function handle()
    {
        $maintenant = Carbon::now();
        $debutSemaine = $maintenant->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $finSemaine = $maintenant->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $employes = Employe::with('user')
            ->whereHas('user', fn($q) => $q->where('statut', 'actif')->where('role', 'employe'))
            ->get();

        $envoyes = 0;

        foreach ($employes as $employe) {
            $aSoumis = DocumentEmploye::where('employe_id', $employe->id)
                ->where('type', 'document_personnel')
                ->whereBetween('created_at', [$debutSemaine, $finSemaine])
                ->exists();

            if ($aSoumis) {
                continue;
            }

            $creation = RapportHebdoRappel::firstOrCreate([
                'employe_id' => $employe->id,
                'annee'      => $maintenant->isoWeekYear,
                'semaine'    => $maintenant->isoWeek,
                'sous_type'  => 'rappel_midi_vendredi',
            ]);

            if ($creation->wasRecentlyCreated) {
                $employe->user->notify(new RapportHebdoRappelNotification('rappel_midi_vendredi'));
                $envoyes++;
            }
        }

        $this->info("Rappels de milieu de journée envoyés : {$envoyes}");
        return self::SUCCESS;
    }
}