<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Component;

class Players extends Component
{
    public $players = [];

    public function mount(): void
    {
        $this->fetchPlayers();
    }

    public function fetchPlayers(): void
    {
        $response = Http::get('api/players');

        if ($response->successful()) {
            $this->players = $response->json();
        } else {
            session()->flash('error', 'Failed to fetch players.');
        }
    }

    public function deletePlayer($id): void
    {
        $response = Http::post("api/players/$id");

        if ($response->successful()) {
            $this->fetchPlayers();
            session()->flash('success', 'Player successfully deleted.');
        } else {
            session()->flash('error', 'Failed to delete player.');
        }
    }

    public function render()
    {
        return view('livewire.players');
    }
}
