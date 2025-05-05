@php
    use App\Http\Enums\PlayerPosition;
    use Illuminate\Support\Collection;
@endphp

<div class="team-management-container">
    @if(!$upcomingMatch)
        <div class="no-match-message">
            <div class="alert alert-info">
                <h4>No upcoming matches scheduled</h4>
                <p>You don't have any upcoming matches to prepare for at the moment.</p>
            </div>
        </div>
    @else
        <div class="team-management-header">
            <h1>Team Management</h1>
            <div class="match-info">
                <h3>Next Match: {{ $upcomingMatch->homeTeam->name }} vs {{ $upcomingMatch->awayTeam->name }}</h3>
                <p class="match-date">{{ $upcomingMatch->match_date->format('l, F j, Y - H:i') }}</p>
            </div>
        </div>

        <div class="tactics-selection">
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <select wire:model.live="selectedFormationId" wire:change="changeFormation" class="input">
                            @foreach($formations as $formation)
                                <option value="{{ $formation->getKey() }}">{{ $formation->name }}</option>
                            @endforeach
                        </select>
                        <label class="input-label">Formation</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <select wire:model.live="selectedTactic" wire:change="changeTactic" class="input">
                            <option value="ATTACK_MODE">Attack Mode</option>
                            <option value="DEFEND_MODE">Defend Mode</option>
                            <option value="DEFAULT_MODE">Default Mode</option>
                        </select>
                        <label class="input-label">Team Tactic</label>
                    </div>
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

        <div class="lineup-container">
            <div class="row">
                <div class="col-md-8">
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
                                     wire:click="removePlayerFromLineup('{{ $position }}')">
                                    <div class="position-label">{{ $this->formatPositionName($position) }}</div>

                                    @if(isset($lineupPlayers[$position]) && $lineupPlayers[$position])
                                        @php
                                            /* @var  Collection|\App\Models\Player[] $players */
                                            $player = $players->firstWhere('id', $lineupPlayers[$position])
                                        @endphp
                                        <div
                                            class="player-card {{ !$this->isPreferredPosition($player->position, $position) ? 'wrong-position' : '' }}">
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

                <div class="col-md-4">
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
                                            <div class="player-card" x-data="{ showMenu: false }">
                                                <div class="player-info" @click="showMenu = !showMenu">
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
                                                </div>

                                                <div class="position-menu" x-show="showMenu"
                                                     @click.away="showMenu = false">
                                                    <div class="menu-title">Assign to Position:</div>
                                                    <div class="menu-options">
                                                        @foreach($positions as $position => $coords)
                                                            <button
                                                                class="position-option {{ !$this->isPreferredPosition($player->position, $position) ? 'not-preferred' : '' }}"
                                                                wire:click="assignPlayer({{ $player->getkey() }}, '{{ $position }}')"
                                                                @click="showMenu = false"
                                                            >
                                                                {{ $this->formatPositionName($position) }}
                                                                @if(!$this->isPreferredPosition($player->position, $position))
                                                                    <i class="bi bi-exclamation-triangle"></i>
                                                                @endif
                                                            </button>
                                                        @endforeach
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
                                                                $currentPosition = array_search($player->getkey(), $lineupPlayers);
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

        <div class="save-lineup-container">
            <button class="button button-primary" wire:click="saveLineup">Save Lineup</button>
        </div>
    @endif
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
