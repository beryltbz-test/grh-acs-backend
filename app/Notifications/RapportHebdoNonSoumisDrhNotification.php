<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RapportHebdoNonSoumisDrhNotification extends Notification
{
    protected $nomEmploye;
    protected $semaine;
    protected $annee;

    public function __construct(string $nomEmploye, int $semaine, int $annee)
    {
        $this->nomEmploye = $nomEmploye;
        $this->semaine = $semaine;
        $this->annee = $annee;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type'    => 'rapport_hebdo_non_soumis_drh',
            'message' => "Rapport hebdomadaire non soumis : {$this->nomEmploye} n'a toujours pas soumis son rapport de la semaine {$this->semaine}/{$this->annee}, malgré l'ultimatum de " . config('rapports.heure_limite_lundi') . ".",
        ];
    }
}