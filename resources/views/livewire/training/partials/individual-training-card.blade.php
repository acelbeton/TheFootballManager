<div class="card mb-4">
    <div class="card-header bg-warning">
        Individual Training (Select 2 Players)
    </div>
    <div class="card-body">
        <div class="player-card-collection">
            @foreach($players as $player)
                @include('livewire.training.partials.training-player-card')
            @endforeach
        </div>

        <div class="mt-4 training-action">
            @if(!$hasTrainedIndividualToday)
                <button wire:click="trainIndividuals"
                        wire:target="trainIndividuals"
                        wire:loading.attr="disabled"
                        class="button button-highlight"
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

            @if(session('individual-training'))
                <div class="ml-3 alert alert-success mt-3">
                    {{ session('individual-training') }}
                </div>
            @endif
        </div>
    </div>
</div>
