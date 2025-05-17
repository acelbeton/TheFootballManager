<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        @vite(['resources/sass/main.scss', 'resources/js/app.js'])
        <title>{{ $title ?? 'Page Title' }}</title>
    </head>
    <body>
        <nav class="custom-nav">
            <div class="nav-container">
                <a href="/" wire:navigate class="nav-brand">Football Manager</a>

                <button class="menu-toggle" aria-label="Toggle menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <div class="nav-items">
                    <a class="nav-link" href="/" wire:navigate>Home</a>

                    @auth
                        <a class="nav-link" href="{{ route('dashboard') }}" wire:navigate>Dashboard</a>
                        <a class="nav-link" href="{{ route('change-team') }}" wire:navigate>Change Team</a>
                        <div class="logout-container">
                            @livewire('auth.logout')
                        </div>
                    @else
                        <a class="nav-link" href="/login" wire:navigate>Login</a>
                        <a class="nav-link" href="/register" wire:navigate>Register</a>
                    @endauth
                </div>
            </div>
        </nav>
        <div class="main-content">
            {{ $slot }}
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const menuToggle = document.querySelector('.menu-toggle');
                const navItems = document.querySelector('.nav-items');

                menuToggle.addEventListener('click', function() {
                    navItems.classList.toggle('show');
                });
            });
        </script>
    </body>
</html>
