<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RapportHebdoRappelNotification extends Notification
{
    protected $sousType;
    protected $heureLimite;

    public function __construct(string $sousType, ?string $heureLimite = null)
    {
        $this->sousType = $sousType;
        $this->heureLimite = $heureLimite;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $messages = [
            'rappel_arrivee_vendredi' => "N'oubliez pas de soumettre votre rapport hebdomadaire dans votre espace \"Mes documents\".",
            'rappel_midi_vendredi'    => "Rappel : votre rapport hebdomadaire n'a pas encore été soumis.",
            'rappel_depart_vendredi'  => "Dernier rappel du jour : n'oubliez pas de soumettre votre rapport hebdomadaire avant la fin de la semaine.",
            'ultimatum_lundi'         => "Votre rapport hebdomadaire de la semaine précédente n'a pas encore été soumis. Vous avez jusqu'à {$this->heureLimite} pour effectuer votre dépôt.",
        ];

        return [
            'type'    => 'rapport_hebdo_rappel',
            'sous_type' => $this->sousType,
            'message' => $messages[$this->sousType] ?? "N'oubliez pas de soumettre votre rapport hebdomadaire.",
        ];
    }
}