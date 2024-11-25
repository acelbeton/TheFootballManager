<?php

namespace App\Livewire;

use Livewire\Component;

class Players extends Component
{
    public $players;

    public function mount(): void
    {
        $this->fetchPlayers();
    }

    public function fetchPlayers(): void
    {
        //        $response
    }

    public function deletePlayer($id): void
    {
        //
    }
    public function render()
    {
        return view('livewire.players');
    }
}
