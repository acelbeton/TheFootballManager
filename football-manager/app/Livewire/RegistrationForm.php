<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Registration')]
class RegistrationForm extends Component
{
    #[Rule('required|string|max:255')]
    public $name;
    #[Rule('required|email|unique:users,email')]
    public $email;
    #[Rule('required|string|confirmed')]
    public $password;
    public $password_confirmation;

    public function register()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
        ]);

        Auth::login($user);

        $this->redirect(route('create-team'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.registration-form');
    }
}
