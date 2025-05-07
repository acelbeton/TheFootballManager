<div class="match-viewer-container">
    <div class="match-header">
        <div class="match-date">
            <span class="date-label">{{ $match->match_date->format('l, F j, Y') }}</span>
            <span class="time-label">{{ $match->match_date->format('H:i') }}</span>
        </div>

        <div class="match-status">
            @if($isMatchLive)
                <span class="live-indicator">LIVE</span>
                <span class="minute-counter">{{ $matchStats['current_minute'] }}'</span>
            @elseif($match->home_team_score > 0 || $match->away_team_score > 0)
                <span class="finished-indicator">FULL TIME</span>
            @else
                <span class="upcoming-indicator">UPCOMING</span>
            @endif
        </div>
    </div>

    <div class="match-scoreboard">
        <div class="team home-team {{ $isUserTeam && Auth::user()->currentTeam->getKey() == $homeTeam->getKey() ? 'user-team' : '' }}">
            <div class="team-name">{{ $homeTeam->name }}</div>
            <div class="team-tactic">{{ str_replace('_', ' ', $homeTeam->current_tactic) }}</div>
        </div>

        <div class="match-score">
            <div class="score-display">
                <span class="home-score">{{ $matchStats['home_score'] }}</span>
                <span class="score-separator">-</span>
                <span class="away-score">{{ $matchStats['away_score'] }}</span>
            </div>

            @if($canStartMatch && !$isMatchLive)
                <button class="button button-primary" wire:click="startMatch" wire:loading.attr="disabled">
                    <span wire:loading.remove>Start Match</span>
                    <span wire:loading>Starting...</span>
                </button>
            @endif
        </div>

        <div class="team away-team {{ $isUserTeam && Auth::user()->currentTeam->getKey() == $awayTeam->getKey() ? 'user-team' : '' }}">
            <div class="team-name">{{ $awayTeam->name }}</div>
            <div class="team-tactic">{{ str_replace('_', ' ', $awayTeam->current_tactic) }}</div>
        </div>
    </div>

    <div class="match-content">
        <div class="row">
            <div class="col-md-8">
                <div class="match-commentary-container">
                    <h3 class="commentary-title">Match Commentary</h3>

                    <div class="commentary-feed" id="commentary-feed">
                        @foreach($matchEvents as $event)
                            <div class="commentary-item {{ $event['type'] }} {{ $event['team'] }}-team">
                                <div class="event-minute">{{ $event['minute'] }}'</div>
                                <div class="event-icon">
                                    @switch($event['type'])
                                        @case('GOAL')
                                            <i class="bi bi-bullseye"></i>
                                            @break
                                        @case('SHOT_ON_TARGET')
                                            <i class="bi bi-record-circle"></i>
                                            @break
                                        @case('SHOT_OFF_TARGET')
                                            <i class="bi bi-record-circle-fill"></i>
                                            @break
                                        @case('CORNER')
                                            <i class="bi bi-flag-fill"></i>
                                            @break
                                        @case('YELLOW_CARD')
                                            <div class="card yellow-card"></div>
                                            @break
                                        @case('RED_CARD')
                                            <div class="card red-card"></div>
                                            @break
                                        @case('GREAT_SAVE')
                                            <i class="bi bi-hand-thumbs-up"></i>
                                            @break
                                        @default
                                            <i class="bi bi-circle"></i>
                                    @endswitch
                                </div>
                                <div class="event-content">
                                    <div class="event-text">{{ $event['commentary'] }}</div>
                                    @if($event['type'] === 'GOAL')
                                        <div class="goal-scorer">
                                            <strong>{{ $event['main_player_name'] }}</strong>
                                            @if(isset($event['secondary_player_name']))
                                                <span class="assist-label">(assist: {{ $event['secondary_player_name'] }})</span>
                                            @endif
                                        </div>
                                        <div class="goal-score">
                                            {{ $homeTeam->name }} {{ $event['home_score'] }} - {{ $event['away_score'] }} {{ $awayTeam->name }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if(empty($matchEvents) && !$isMatchLive)
                            <div class="no-events-message">
                                @if($match->home_team_score > 0 || $match->away_team_score > 0)
                                    No detailed match events available for this match.
                                @else
                                    The match hasn't started yet. Stay tuned for live commentary!
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="match-stats-container">
                    <h3 class="stats-title">Match Stats</h3>

                    <div class="stat-item">
                        <div class="stat-label">Possession</div>
                        <div class="stat-bars">
                            <div class="stat-bar-container">
                                <div class="stat-bar home-bar" style="width: {{ $matchStats['home_possession'] }}%"></div>
                                <div class="stat-value home-value">{{ $matchStats['home_possession'] }}%</div>
                            </div>
                            <div class="stat-bar-container">
                                <div class="stat-bar away-bar" style="width: {{ $matchStats['away_possession'] }}%"></div>
                                <div class="stat-value away-value">{{ $matchStats['away_possession'] }}%</div>
                            </div>
                        </div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-label">Shots</div>
                        <div class="stat-values">
                            <div class="home-value">{{ $matchStats['home_shots'] }}</div>
                            <div class="away-value">{{ $matchStats['away_shots'] }}</div>
                        </div>
                    </div>

                    <div class="stat-item">
                        <div class="stat-label">Shots on Target</div>
                        <div class="stat-values">
                            <div class="home-value">{{ $matchStats['home_shots_on_target'] }}</div>
                            <div class="away-value">{{ $matchStats['away_shots_on_target'] }}</div>
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

    <script>
        document.addEventListener('livewire:update', function() {
            const feed = document.getElementById('commentary-feed');
            if (feed) {
                feed.scrollTop = feed.scrollHeight;
            }
        });
    </script>
</div>
