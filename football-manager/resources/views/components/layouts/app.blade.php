<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        @vite('resources/sass/main.scss')
        <title>{{ $title ?? 'Page Title' }}</title>
    </head>
    <body>
        <nav class="custom-nav">
            <div class="nav-container">
                <a href="/" wire:navigate class="nav-brand">Football Manager</a>
                <ul class="nav-list">
                    <li class="nav-item">
                        <a class="nav-link" href="/" wire:navigate>Home</a>
                    </li>
                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('dashboard') }}" wire:navigate>Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('change-team') }}" wire:navigate>Change Team</a>
                        </li>
                        <li class="nav-item">
                            @livewire('auth.logout')
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="/login" wire:navigate>Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register" wire:navigate>Register</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </nav>
        <div class="main-content">
            {{ $slot }}
        </div>
    </body>
</html>
