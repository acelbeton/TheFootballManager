<div class="team-card m-3">
    <h3 class="p-2 d-flex justify-content-center">{{ $team->name }}</h3>
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-label">League</div>
            <div class="stat-value">
                {{ $team->season->league->name ?? 'N/A' }}
            </div>
        </div>

        <div class="stat-item">
            <div class="stat-label">Points</div>
            <div class="stat-value">
                {{ $team->season->standing->points ?? 0 }}
            </div>
        </div>

        <div class="stat-item">
            <div class="stat-label">Strength</div>
            <div class="stat-value">
                {{ $team->team_rating }}<small>/100</small>
            </div>
        </div>

        <div class="stat-item">
            <div class="stat-label">Players</div>
            <div class="stat-value">
                {{ $team->players->count() }}
            </div>
        </div>
    </div>
    <div class="team-card-footer">
        <button
            wire:click="changeCurrentTeam({{ $team->getKey() }})"
            class="button button-primary px-4 py-2 mr-2"
        >
            Select
        </button>

{{--        @if(!$team->season)--}}
            <button
                class="button button-warning"
                wire:click="$dispatch('openDeleteConfirmation', {
                    teamId: {{ $team->getKey() }},
                    teamName: '{{ addslashes($team->name) }}'
                })"
            >
                Delete
            </button>
{{--        @endif--}}
    </div>
</div>
