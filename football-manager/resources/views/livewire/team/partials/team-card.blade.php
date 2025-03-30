{{--@php--}}

{{--@endphp--}}

<div class="card">
    <h3>{{ $team->name }}</h3>
    <div class="card-body">
        <p>League: {{ $team->season->league->name ?? 'N/A' }}</p>
        {{--    <p>Formation: {{ $team->formation }}</p>--}}
        <p>Points: {{ $team->season->standing->points ?? 0 }}</p>
        <p>Team Strength: {{ $team->team_rating }}</p>
        <p>Players: {{ $team->players->count() }}</p>
    </div>
</div>
