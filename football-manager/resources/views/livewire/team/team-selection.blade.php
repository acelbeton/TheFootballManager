<div class="container d-flex justify-content-center align-content-between">
    @foreach($teams as $team)
        @include('livewire.team.partials.team-card')
    @endforeach
</div>
