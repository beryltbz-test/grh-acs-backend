<?php

namespace App\Console\Commands;

use App\Models\Employe;
use App\Models\DocumentEmploye;
use App\Models\RapportHebdoRappel;
use App\Models\User;
use App\Notifications\RapportHebdoNonSoumisDrhNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifierRapportsHebdoLundi extends Command
{
    protected $signature = 'rapports:verifier-lundi';
    protected $description = "Notifie la DRH des employés n'ayant toujours pas soumis leur rapport hebdomadaire après l'ultimatum du lundi";

    public function handle()
    {
        $maintenant = Carbon::now();
        $semaineRef = $maintenant->copy()->subWeek(); // la semaine concernée est la précédente
        $debutSemaine = $semaineRef->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $finSemaine = $semaineRef->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $employes = Employe::with('user')
            ->whereHas('user', fn($q) => $q->where('statut', 'actif')->where('role', 'employe'))
            ->get();

        $drh = User::where('statut', 'actif')->where('role', 'drh')->get();

        if ($drh->isEmpty()) {
            $this->warn("Aucun compte DRH actif trouvé — aucune notification envoyée.");
            return self::SUCCESS;
        }

        $signales = 0;

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
                'annee'      => $semaineRef->isoWeekYear,
                'semaine'    => $semaineRef->isoWeek,
                'sous_type'  => 'notification_drh',
            ]);

            if (!$creation->wasRecentlyCreated) {
                continue; // déjà signalé
            }

            foreach ($drh as $unDrh) {
                $unDrh->notify(new RapportHebdoNonSoumisDrhNotification(
                    $employe->user->name,
                    $semaineRef->isoWeek,
                    $semaineRef->isoWeekYear
                ));
            }
            $signales++;
        }

        $this->info("Employés signalés à la DRH : {$signales}");
        return self::SUCCESS;
    }
}