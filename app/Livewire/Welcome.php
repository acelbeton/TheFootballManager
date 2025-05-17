<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Football Manager')]
class Welcome extends Component
{
    public function render()
    {
        return view('livewire.welcome');
    }
}
