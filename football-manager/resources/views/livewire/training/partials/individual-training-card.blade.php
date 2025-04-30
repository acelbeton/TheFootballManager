<div class="card mb-4">
    <div class="card-header bg-warning">
        Individual Training (Select 2 Players)
    </div>
    <div class="card-body">
        <div class="row">
            @foreach($players as $player)
                @include('livewire.training.partials.training-player-card')
            @endforeach
        </div>

        @if(!$hasTrainedIndividualToday)
            <button wire:click="trainIndividuals"
                    class="btn btn-success"
                {{ count($selectedPlayers) !== 2 ? 'disabled' : '' }}>
                Train Selected Players ({{ count($selectedPlayers) }}/2)
            </button>
        @else
            <div class="alert alert-info">
                Individual training completed for today
            </div>
        @endif
    </div>
</div>
