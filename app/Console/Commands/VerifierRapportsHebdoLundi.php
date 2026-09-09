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
    protected $description = "Notifie la DRH (ou le Directeur si c'est la DRH qui est en défaut) des employés n'ayant toujours pas soumis leur rapport hebdomadaire après l'ultimatum du lundi";

    public function handle()
    {
        $maintenant = Carbon::now();
        $semaineRef = $maintenant->copy()->subWeek();
        $debutSemaine = $semaineRef->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $finSemaine = $semaineRef->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $employes = Employe::with('user')
            ->whereHas('user', fn($q) => $q->where('statut', 'actif')->whereIn('role', ['employe', 'drh']))
            ->get();

        $drh = User::where('statut', 'actif')->where('role', 'drh')->get();
        $directeurs = User::where('statut', 'actif')->where('role', 'directeur')->get();

        $signales = 0;

        foreach ($employes as $employe) {
            $aSoumis = DocumentEmploye::where('employe_id', $employe->id)
                ->where('type', 'rapport_hebdomadaire')
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

            // Si c'est un DRH qui est en défaut, on remonte au Directeur ; sinon, à la DRH.
            $destinataires = $employe->user->role === 'drh' ? $directeurs : $drh;

            if ($destinataires->isEmpty()) {
                $this->warn("Aucun destinataire actif ({$employe->user->role === 'drh' ? 'Directeur' : 'DRH'}) pour signaler {$employe->user->name} — notification non envoyée.");
                continue;
            }

            foreach ($destinataires as $destinataire) {
                $destinataire->notify(new RapportHebdoNonSoumisDrhNotification(
                    $employe->user->name,
                    $semaineRef->isoWeek,
                    $semaineRef->isoWeekYear
                ));
            }
            $signales++;
        }

        $this->info("Personnes signalées : {$signales}");
        return self::SUCCESS;
    }
}