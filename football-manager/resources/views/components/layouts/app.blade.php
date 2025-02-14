<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Page Title' }}</title>
    </head>
    <body>
    <nav>
        <a href="/" wire:navigate>Home</a>
        @auth
            @livewire('auth.logout')
        @else
            <a href="/login" wire:navigate>Login</a>
            <a href="/register" wire:navigate>Register</a>
        @endauth
    </nav>
        {{ $slot }}
    </body>
</html>
