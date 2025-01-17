@extends('layouts.app')

@section('content')
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center">
                <h1>Welcome to Football Manager</h1>
                <p class="lead">Manage your team, compete in leagues, and become the best manager in the world!</p>

                @guest
                    <div class="mt-4">
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg me-2">Login</a>
                        <a href="{{ route('register') }}" class="btn btn-outline-secondary btn-lg">Register</a>
                    </div>
                @endguest

                @auth
                    <div class="mt-4">
                        <a href="{{ route('dashboard') }}" class="btn btn-success btn-lg">Go to Dashboard</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
@endsection
