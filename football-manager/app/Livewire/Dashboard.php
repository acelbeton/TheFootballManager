<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Welcome')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.dashboard');
    }
}
