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
        @endif
    </div>
</div>
