@php
    use App\Http\Enums\PlayerPosition;
    use App\Models\Player;
    use Illuminate\Support\Collection;
@endphp

<div class="team-management-container">
    <div class="dashboard-header section-card">
        <div class="header-content">
            <h1>Default Team Lineup</h1>
            <div class="team-meta">
                <div class="team-rating">
                    <span class="label">Team Rating:</span>
                    <span class="value">{{ $team->team_rating }}/100</span>
                </div>
                <div class="team-tactic">
                    <span class="label">Team Name:</span>
                    <span class="value">{{ $team->name }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="instructions-panel m-4">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <span>
                <strong>How to use:</strong>
                Click on a player in the squad list, then click on a position to assign them.
                Click on a player on the pitch to remove them.
                Changes are saved automatically.
            </span>
        </div>
    </div>

    <div class="tactics-selection">
        <div class="row">
            <div class="col-md-6">
                <x-custom-select
                    label="Formation"
                    name="formation"
                    :options="$formations"
                    wire:model.live="selectedFormationId"
                    wire:change="changeFormation"
                />
            </div>
            <div class="col-md-6">
                <x-custom-select
                    label="Team Tactic"
                    name="team_tactic"
                    :options="$teamTactic"
                    wire:model.live="selectedTactic"
                    wire:change="changeTactic"
                />
            </div>
        </div>
    </div>

    @if($showWarning)
        <div class="position-warning" x-data="{ show: true }" x-show="show"
             x-init="setTimeout(() => show = false, 5000)">
            <div class="alert alert-warning">
                <span class="warning-icon"><i class="bi bi-exclamation-triangle"></i></span>
                <span class="warning-message">{{ $warningMessage }}</span>
                <button type="button" class="close-btn" @click="show = false"><i class="bi bi-x"></i></button>
            </div>
        </div>
    @endif

    @if (isset($selectedPlayerForAssignment))
        @php
            /* @var  Collection|Player[] $players */
            $selectedPlayer = $players->firstWhere('id', $selectedPlayerForAssignment);
        @endphp
        <div class="player-selection-banner" x-data x-init="$el.classList.add('animate-in')">
            <div class="selection-card">
                <div class="player-avatar">
                    <div
                        class="player-position-badge {{ strtolower(str_replace('_', '-', $selectedPlayer->position)) }}">
                        {{ substr(PlayerPosition::getName($selectedPlayer->position), 0, 2) }}
                    </div>
                </div>
                <div class="selection-content">
                    <div class="selection-player">
                        <span class="player-name">{{ $selectedPlayer->name }}</span>
                        <span class="player-rating">{{ $selectedPlayer->rating }}</span>
                    </div>
                    <div class="selection-instruction">
                        <i class="bi bi-arrow-right-circle me-1"></i>
                        <span>Click on any position on the field</span>
                    </div>
                </div>
                <button type="button" class="selection-cancel" wire:click="cancelPlayerSelection">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    @endif
    <div class="lineup-container">
        <div class="row g-3">
            <div class="col-md-7">
                <div class="pitch-container">
                    <div class="football-pitch">
                        <div class="center-circle"></div>
                        <div class="center-spot"></div>
                        <div class="center-line"></div>

                        <div class="penalty-area penalty-area-left"></div>
                        <div class="penalty-area penalty-area-right"></div>
                        <div class="goal-area goal-area-left"></div>
                        <div class="goal-area goal-area-right"></div>
                        <div class="penalty-spot penalty-spot-left"></div>
                        <div class="penalty-spot penalty-spot-right"></div>

                        @foreach($positions as $position => $coords)
                            <div class="player-position" style="left: {{ $coords[0] }}%; top: {{ $coords[1] }}%;"
                                 @if(isset($selectedPlayerForAssignment))
                                     wire:click="assignPlayerToPosition('{{ $position }}')"
                                @endif>
                                <div class="position-label">{{ $this->formatPositionName($position) }}</div>

                                @if(isset($lineupPlayers[$position]) && $lineupPlayers[$position])
                                    @php
                                        /* @var  Collection|Player[] $players */
                                        $player = $players->firstWhere('id', $lineupPlayers[$position])
                                    @endphp
                                    <div
                                        class="player-card {{ !$this->isPreferredPosition($player->position, $position) ? 'wrong-position' : '' }}"
                                        wire:click="removePlayerFromLineup('{{ $position }}')">
                                        <div class="player-number">{{ $loop->iteration }}</div>
                                        <div class="player-name">{{ $player->name }}</div>
                                        <div class="player-rating">{{ $player->rating }}</div>
                                    </div>
                                @else
                                    <div class="empty-position">
                                        <i class="bi bi-plus-circle"></i>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="squad-selection">
                    <h3>Squad Selection</h3>

                    <div class="position-filters" x-data="{ activeTab: 'ALL' }">
                        <div class="position-tabs">
                            <button class="position-tab" :class="{ 'active': activeTab === 'ALL' }"
                                    @click="activeTab = 'ALL'">All
                            </button>
                            @foreach(PlayerPosition::cases() as $position)
                                <button class="position-tab"
                                        :class="{ 'active': activeTab === '{{ $position->value }}' }"
                                        @click="activeTab = '{{ $position->value }}'">
                                    {{ PlayerPosition::getName($position->name) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="players-list">
                            @foreach($players as $player)
                                @if(in_array($player->getKey(), $benchPlayers))
                                    <div class="player-card-container"
                                         x-show="activeTab === 'ALL' || activeTab === '{{ $player->position }}'">
                                        <div
                                            class="player-card {{ $selectedPlayerForAssignment == $player->getKey() ? 'selected' : '' }}">
                                            <div class="player-info"
                                                 wire:click="selectPlayerForAssignment({{ $player->getKey() }})">
                                                <div
                                                    class="player-position-badge {{ strtolower(str_replace('_', '-', $player->position)) }}">
                                                    {{ substr(PlayerPosition::getName($player->position), 0, 2) }}
                                                </div>
                                                <div class="player-details">
                                                    <div class="player-name">{{ $player->name }}</div>
                                                    <div class="player-attributes">
                                                        <span class="player-rating">{{ $player->rating }}</span>
                                                        @if($player->is_injured)
                                                            <span class="player-injury"><i
                                                                    class="bi bi-bandaid"></i></span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="assign-button">
                                                    <i class="bi bi-plus-circle"></i>
                                                    <span>Select</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach

                            @foreach($players as $player)
                                @if(!in_array($player->getKey(), $benchPlayers))
                                    <div class="player-card-container"
                                         x-show="activeTab === 'ALL' || activeTab === '{{ $player->position }}'">
                                        <div class="player-card in-lineup">
                                            <div class="player-info">
                                                <div
                                                    class="player-position-badge {{ strtolower(str_replace('_', '-', $player->position)) }}">
                                                    {{ substr(PlayerPosition::getName($player->position), 0, 2) }}
                                                </div>
                                                <div class="player-details">
                                                    <div class="player-name">{{ $player->name }}</div>
                                                    <div class="player-attributes">
                                                        <span class="player-rating">{{ $player->rating }}</span>
                                                        @php
                                                            $currentPosition = array_search($player->getKey(), $lineupPlayers);
                                                        @endphp
                                                        <span
                                                            class="current-position">Playing as {{ $this->formatPositionName($currentPosition) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        x-data="{
        show: false,
        message: '',
        type: 'info',
        init() {
            window.addEventListener('notify', (event) => {
                this.message = event.detail.message;
                this.type = event.detail.type;
                this.show = true;
                setTimeout(() => { this.show = false }, 3000);
            })
        }
    }"
        x-show="show"
        x-transition
        class="notification-toast"
        :class="type"
        x-text="message">
    </div>
</div>
