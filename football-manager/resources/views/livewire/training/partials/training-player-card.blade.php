<div class="col-md-6 mb-3">
    <div class="card player-card {{ collect($selectedPlayers)->contains($player->getKey()) ? 'border-success' : '' }}">
        <div class="card-body">
            <div class="form-check">
                <input wire:model="selectedPlayers"
                       type="checkbox"
                       value="{{ $player->getKey() }}"
                       class="form-check-input"
                    {{ collect($selectedPlayers)->contains($player->getKey()) ? 'checked' : '' }}
                    {{ $hasTrainedIndividualToday ? 'disabled' : '' }}>
            </div>
            <h5>{{ $player->name }}</h5>
            <p class="mb-1">Position: {{ $player->position }}</p>
            <small class="text-muted">
                Condition: {{ $player->condition }}%
                <div class="progress">
                    <div class="progress-bar"
                         role="progressbar"
                         style="width: {{ $player->condition }}%">
                    </div>
                </div>
            </small>
        </div>
    </div>
</div>
