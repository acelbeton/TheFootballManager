@php
    use App\Http\Enums\PlayerPosition;
@endphp

<div class="col-md-4 mb-3">
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
            <p><b>{{ $player->name }}</b></p>
            <p class="mb-1">Position: {{ PlayerPosition::getName($player->position) }}</p>
            <div class="stats-grid">
                @foreach($statisticNames as $stat)
                    <div class="stat-child">
                        <span class="stat-label"><b>{{ $stat->label() }}</b></span> <br/>
                        <span class="player-stat">{{ optional($player->statistics)->{$stat->value} ?? 'N/A' }}</span>
                    </div>
                @endforeach
            </div>
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
