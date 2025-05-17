@php
    use App\Http\Enums\PlayerPosition;
    use App\Models\Player;
    use Illuminate\Support\Collection;

    /* @var  Collection|Player[] $player */
    $playerId = $player->getKey();
    $hasStatChanges = isset($trainingResults[$playerId]) && count($trainingResults[$playerId]['stats']) > 0;
    $conditionChange = isset($trainingResults[$playerId]) ? $trainingResults[$playerId]['condition'] : 0;
@endphp

<div class="player-card-wrapper">
    <div
        class="card player-card {{ in_array($player->getKey(), $selectedPlayers) ? 'selected' : '' }} {{ $hasStatChanges ? 'has-stat-changes' : '' }}"
        wire:loading.class.delay="opacity-50"
        x-data="{ showTrainingResults: {{ $hasStatChanges ? 'true' : 'false' }} }"
        x-init="
            Livewire.on('training-completed', () => {
                setTimeout(() => {
                    showTrainingResults = true;
                }, 500);
            });
         ">
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
                    @php
                        $statName = $stat->value;
                        $statImprovement = $hasStatChanges && isset($trainingResults[$playerId]['stats'][$statName]) ? $trainingResults[$playerId]['stats'][$statName] : 0;
                    @endphp
                    <div class="player-stat-item {{ $statImprovement > 0 ? 'stat-improved' : '' }}"
                         data-bs-toggle="tooltip"
                         title="{{ $stat->label() }}">
                        <div class="stat-content">
                            <span class="player-stat-label">{{ $stat->abbreviation() }}</span>
                            <span class="player-stat-value">
                                {{ optional($player->statistics)->{$stat->value} ?? 0 }}
                                @if($statImprovement > 0)
                                    <span class="stat-improvement" x-show="showTrainingResults">
                                        +{{ $statImprovement }}
                                    </span>
                                @endif
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="condition-meter mt-auto">
                <div class="d-flex justify-content-between mb-2">
                    <small>Condition</small>
                    <div>
                        <small>{{ $player->condition }}%</small>
                        @if($conditionChange < 0)
                            <small class="condition-change" x-show="showTrainingResults">
                                {{ $conditionChange }}
                            </small>
                        @endif
                    </div>
                </div>
                <div class="progress">
                    <div
                        class="progress-bar bg-{{ $player->condition >= 60 ? 'success' : ($player->condition >= 30 ? 'warning' : 'danger') }}"
                        role="progressbar"
                        style="width: {{ $player->condition }}%">
                    </div>
                </div>
            </div>
        </div>

        @if($hasStatChanges)
            <div class="training-result-badge" x-show="showTrainingResults">
                <i class="fas fa-arrow-up"></i> Improved
            </div>
        @endif
    </div>
</div>
