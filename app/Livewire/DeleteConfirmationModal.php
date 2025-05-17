<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class DeleteConfirmationModal extends Component
{
    public $show = false;
    public $teamId;
    public $teamName;

    protected $listeners = ['openDeleteConfirmation' => 'open'];

    public function open($teamId, $teamName): void
    {
        $this->teamId = $teamId;
        $this->teamName = $teamName;
        $this->show = true;
    }

    public function closeModal(): void
    {
        $this->show = false;
        $this->reset(['teamId', 'teamName']);
    }

    public function delete(): void
    {
        $this->dispatch('deleteTeamConfirmed', teamId: $this->teamId);
        $this->dispatch('refreshTeams');
        $this->closeModal();
    }

    public function render(): View
    {
        return view('livewire.delete-confirmation-modal');
    }
}
