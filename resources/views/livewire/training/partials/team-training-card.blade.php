<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        Team Training
    </div>
    <div class="card-body">
        <p class="card-text">
            Train your entire team once per day.
            <span class="text-muted">(Random stat boost, Random stamina decrease for all players)</span>
        </p>

        @if(!$hasTrainedTeamToday)
            <button wire:click="trainTeam" class="button button-primary">
                Start Team Training
            </button>
        @else
            <div class="alert alert-info">
                Team already trained today. Next available: {{ $nextTrainingTime }}
            </div>

            @if(count($trainingResults) > 0)
                <div class="team-training-results mt-3">
                    <h5>Training Results:</h5>
                    <div class="team-improvements-summary">
                        @php
                            $improvedPlayers = 0;
                            foreach ($trainingResults as $playerId => $result) {
                                if (count($result['stats']) > 0) {
                                    $improvedPlayers++;
                                }
                            }
                        @endphp
                        <p class="mb-2">
                            <span class="badge bg-success">{{ $improvedPlayers }}</span> players improved their skills
                        </p>
                    </div>
                </div>
            @endif
        @endif

        @if(session('team-training'))
            <div class="alert alert-success mt-3">
                {{ session('team-training') }}
            </div>
        @endif
    </div>
</div>
