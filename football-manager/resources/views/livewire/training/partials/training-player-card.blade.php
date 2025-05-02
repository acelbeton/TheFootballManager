@php
    use App\Http\Enums\PlayerPosition;
@endphp

<div class="col mb-3">
    <div class="card h-100 player-card {{ in_array($player->getKey(), $selectedPlayers) ? 'selected' : '' }}"
         wire:loading.class.delay="opacity-50">
        <div class="player-card-header">
            <div class="header-top">
                <div class="form-check form-switch">
                    <input
                        wire:model.defer="selectedPlayers"
                        type="checkbox"
                        value="{{ $player->getKey() }}"
                        class="form-check-input"
                        @checked(in_array($player->getKey(), $selectedPlayers))
                        @disabled($hasTrainedIndividualToday)
                        x-data
                        x-on:change="
                            $nextTick(() => {
                                $dispatch('selected-players-updated', { count: $wire.selectedPlayers.length });
                            });
                        "
                    >
                </div>
                <span class="position-badge">{{ PlayerPosition::getName($player->position) }}</span>
            </div>
            <h3 class="player-name">{{ $player->name }}</h3>
        </div>

        <div class="card-body">
            <div class="stats-grid">
                @foreach($statisticNames as $stat)
                    <div class="player-stat-item" data-bs-toggle="tooltip" title="{{ $stat->label() }}">
                        <div class="stat-content">
                            <span class="player-stat-label">{{ $stat->abbreviation() }}</span>
                            <span class="player-stat-value">
                                {{ optional($player->statistics)->{$stat->value} ?? 0 }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="condition-meter mt-auto">
                <div class="d-flex justify-content-between mb-2">
                    <small>Condition</small>
                    <small>{{ $player->condition }}%</small>
                </div>
                <div class="progress">
                    <div class="progress-bar bg-{{ $player->condition >= 60 ? 'success' : ($player->condition >= 30 ? 'warning' : 'danger') }}"
                         role="progressbar"
                         style="width: {{ $player->condition }}%">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
