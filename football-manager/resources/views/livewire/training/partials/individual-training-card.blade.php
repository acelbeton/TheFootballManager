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
                    wire:target="trainIndividuals"
                    wire:loading.attr="disabled"
                    class="btn btn-success"
                    x-data="{ count: @js(count($selectedPlayers)) }"
                    x-init="
                        document.addEventListener('selected-players-updated', (e) => {
                            count = e.detail.count;
                        });
                    "
                    x-bind:disabled="count !== 2">
                Train Selected Players (<span x-text="count"></span>/2)
            </button>
        @else
            <div class="alert alert-info">
                Individual training completed for today
            </div>
        @endif
    </div>
</div>
