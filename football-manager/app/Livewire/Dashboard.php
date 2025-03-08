<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Welcome')]
class Dashboard extends Component
{
    protected $team;
    public function mount() {
        $this->team = auth()->user()->team; // todo hogyan listázzuk ki a csapatot?
    }
    public function render()
    {
        return view('livewire.dashboard');
    }
}
