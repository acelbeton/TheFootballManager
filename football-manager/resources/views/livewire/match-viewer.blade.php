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
            @elseif($status === 'COMPLETED')
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
                        <div>
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
                        </div>

                        <div id="js-commentary-feed"></div>

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
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("Match viewer initialized");

        const matchViewer = {
            events: [],
            displayedEvents: [],
            pendingEvents: [],
            currentMinute: 0,
            matchId: {{ $match->id }},
            isLive: false,
            minuteUpdateTimer: null,
            eventDisplayTimer: null,
            recentEventsLimit: 5,
            showAllEvents: false,
            eventDisplayDelay: 2000,
            isProcessingEvent: false,

            init: function() {
                this.setupEventListeners();

                this.events = @json($matchEvents) || [];
                console.log("Initial events:", this.events.length);

                this.renderInitialCommentary();

                const liveIndicator = document.querySelector('.live-indicator');
                const finishedIndicator = document.querySelector('.finished-indicator');

                if (liveIndicator) {
                    this.isLive = true;

                    const minuteCounter = document.querySelector('.minute-counter');
                    if (minuteCounter) {
                        this.currentMinute = parseInt(minuteCounter.textContent) || 0;
                    }

                    this.startMinuteUpdater();
                } else if (finishedIndicator) {
                    this.currentMinute = 90;
                    this.renderFullTimeEvents();
                }
            },

            setupEventListeners: function() {
                const startButton = document.querySelector('button[wire\\:click="startMatch"]');
                if (startButton) {
                    console.log("Start button event listener added");
                    startButton.addEventListener('click', () => {
                        console.log("Start match button clicked");

                        const upcomingIndicator = document.querySelector('.upcoming-indicator');
                        if (upcomingIndicator) {
                            upcomingIndicator.textContent = 'LIVE';
                            upcomingIndicator.className = 'live-indicator';
                        }

                        const minuteCounter = document.querySelector('.minute-counter');
                        if (minuteCounter) {
                            minuteCounter.textContent = '0\'';
                            minuteCounter.style.display = 'inline-block';
                        }

                        setTimeout(() => {
                            this.addKickoffCommentary();
                            this.isLive = true;

                            this.startMinuteUpdater();
                        }, 1000);
                    });
                }

                document.addEventListener('livewire:init', () => {
                    console.log("Setting up Livewire event listeners");

                    Livewire.on('match_updated', (eventData) => {
                        console.log("Match update received:", eventData);

                        if (eventData && eventData.events && eventData.events.length > 0) {
                            this.processNewEvents(eventData.events);
                        }
                    });

                    Livewire.on('match-started', () => {
                        console.log("Match started event received");
                        this.isLive = true;

                        const upcomingIndicator = document.querySelector('.upcoming-indicator');
                        if (upcomingIndicator) {
                            upcomingIndicator.textContent = 'LIVE';
                            upcomingIndicator.className = 'live-indicator';
                        }

                        const minuteCounter = document.querySelector('.minute-counter');
                        if (minuteCounter) {
                            minuteCounter.textContent = '0\'';
                            minuteCounter.style.display = 'inline-block';
                        }

                        this.addKickoffCommentary();

                        this.startMinuteUpdater();
                    });

                    if (window.Echo) {
                        console.log("Setting up Echo listener for channel match." + this.matchId);
                        window.Echo.join(`match.${this.matchId}`)
                            .listen('.MatchStatusUpdate', (update) => {
                                console.log("Echo received match update:", update);
                                this.handleMatchUpdate(update);
                            });
                    }
                });
            },

            renderInitialCommentary: function() {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                const finishedIndicator = document.querySelector('.finished-indicator');

                if (finishedIndicator) {
                    this.showAllEvents = true;
                    this.renderFullTimeEvents();
                } else if (!this.isLive && this.events.length > 0) {
                    const sortedEvents = [...this.events].sort((a, b) => a.minute - b.minute);
                    sortedEvents.forEach(event => {
                        this.displayEvent(event, false);
                        this.displayedEvents.push(event);
                    });
                } else if (!this.isLive) {
                    const item = document.createElement('div');
                    item.className = 'commentary-item GENERIC neutral-team';
                    item.innerHTML = `
                    <div class="event-minute">0'</div>
                    <div class="event-icon"><i class="bi bi-circle"></i></div>
                    <div class="event-content">
                        <div class="event-text">Match will begin soon. Stay tuned for live commentary!</div>
                    </div>
                `;
                    feed.appendChild(item);
                }
            },

            renderCommentary: function() {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                feed.innerHTML = '';

                if (this.events.length === 0) {
                    const item = document.createElement('div');
                    item.className = 'commentary-item GENERIC neutral-team';
                    item.innerHTML = `
                    <div class="event-minute">0'</div>
                    <div class="event-icon"><i class="bi bi-circle"></i></div>
                    <div class="event-content">
                        <div class="event-text">Match will begin soon. Stay tuned for live commentary!</div>
                    </div>
                `;
                    feed.appendChild(item);
                    return;
                }

                const sortedEvents = [...this.events].sort((a, b) => a.minute - b.minute);

                let eventsToDisplay;
                if (this.isLive && !this.showAllEvents) {
                    eventsToDisplay = sortedEvents.slice(-this.recentEventsLimit);

                    if (sortedEvents.length > this.recentEventsLimit && !document.getElementById('toggle-events-btn')) {
                        this.addToggleButton(feed);
                    }
                } else {
                    eventsToDisplay = sortedEvents;
                }

                if (this.showAllEvents || !this.isLive || sortedEvents.length <= this.recentEventsLimit) {
                    this.addKickoffCommentary();
                }

                eventsToDisplay.forEach(event => {
                    this.displayEvent(event, false);
                });

                if (this.showAllEvents && sortedEvents.some(e => e.minute >= 45)) {
                    this.addHalftimeCommentary();
                }

                if (!this.isLive) {
                    this.addFulltimeCommentary();
                }

                this.scrollToBottom(feed);
            },

            processNewEvents: function(newEvents) {
                const existingEventIds = this.displayedEvents.map(e =>
                    `${e.type}-${e.minute}-${e.main_player_id || 'none'}-${e.commentary || ''}`
                );

                const eventsToAdd = newEvents.filter(e => {
                    const eventId = `${e.type}-${e.minute}-${e.main_player_id || 'none'}-${e.commentary || ''}`;
                    return !existingEventIds.includes(eventId);
                });

                console.log(`Found ${eventsToAdd.length} new events to display`);

                this.events = newEvents;

                eventsToAdd.forEach(event => {
                    this.pendingEvents.push(event);
                    this.displayedEvents.push(event);
                });

                if (!this.isProcessingEvent && this.isLive) {
                    this.processNextPendingEvent();
                }

                if (!this.isLive) {
                    this.showAllEvents = true;
                    this.pendingEvents = [];
                    this.renderCommentary();
                }

                if (newEvents.some(e => e.minute >= 45) && !this.displayedEvents.some(e => e.type === 'GENERIC' && e.commentary && e.commentary.includes('Half time'))) {
                    this.addHalftimeCommentary();
                }
            },

            processNextPendingEvent: function() {
                if (this.pendingEvents.length === 0) {
                    this.isProcessingEvent = false;
                    return;
                }

                this.isProcessingEvent = true;
                const event = this.pendingEvents.shift();

                this.displayEvent(event, true);

                setTimeout(() => {
                    this.processNextPendingEvent();
                }, this.eventDisplayDelay);
            },

            handleMatchUpdate: function(update) {
                console.log("Processing match update:", update);

                const matchData = update.payload || update;

                if (matchData.current_minute !== undefined) {
                    this.currentMinute = matchData.current_minute;
                    const minuteCounter = document.querySelector('.minute-counter');
                    if (minuteCounter) {
                        minuteCounter.textContent = `${this.currentMinute}'`;
                    }
                }

                if (matchData.home_team && matchData.away_team) {
                    if (matchData.home_team.score !== undefined) {
                        const homeScore = document.querySelector('.home-score');
                        if (homeScore) homeScore.textContent = matchData.home_team.score;
                    }

                    if (matchData.away_team.score !== undefined) {
                        const awayScore = document.querySelector('.away-score');
                        if (awayScore) awayScore.textContent = matchData.away_team.score;
                    }

                    this.updateStats(matchData);
                }


                if (matchData.event) {
                    const eventId = `${matchData.event.type}-${matchData.event.minute}-${matchData.event.main_player_id || 'none'}-${matchData.event.commentary || ''}`;
                    const isNewEvent = !this.displayedEvents.some(e =>
                        `${e.type}-${e.minute}-${e.main_player_id || 'none'}-${e.commentary || ''}` === eventId
                    );

                    if (isNewEvent) {
                        console.log("New event received:", matchData.event);
                        this.pendingEvents.push(matchData.event);
                        this.displayedEvents.push(matchData.event);
                        this.events.push(matchData.event);

                        if (!this.isProcessingEvent && this.isLive) {
                            this.processNextPendingEvent();
                        }
                    }
                }

                if (matchData.type) {
                    if (matchData.type === 'MATCH_START') {
                        this.isLive = true;

                        const upcomingIndicator = document.querySelector('.upcoming-indicator');
                        if (upcomingIndicator) {
                            upcomingIndicator.textContent = 'LIVE';
                            upcomingIndicator.className = 'live-indicator';
                        }

                        this.addKickoffCommentary();
                    }
                    else if (matchData.type === 'HALF_TIME') {
                        this.addHalftimeCommentary();
                    }
                    else if (matchData.type === 'MATCH_END') {
                        this.endMatch();
                    }
                }
            },

            addToggleButton: function(feed) {
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'toggle-events-container';
                buttonContainer.style.textAlign = 'center';
                buttonContainer.style.marginBottom = '10px';

                const button = document.createElement('button');
                button.id = 'toggle-events-btn';
                button.className = 'btn btn-sm btn-outline-primary';
                button.textContent = this.showAllEvents ? 'Show Recent Events' : 'Show All Events';
                button.addEventListener('click', () => this.toggleEventDisplay());

                buttonContainer.appendChild(button);

                if (feed.firstChild) {
                    feed.insertBefore(buttonContainer, feed.firstChild);
                } else {
                    feed.appendChild(buttonContainer);
                }
            },

            toggleEventDisplay: function() {
                this.showAllEvents = !this.showAllEvents;
                this.renderCommentary();

                const toggleButton = document.getElementById('toggle-events-btn');
                if (toggleButton) {
                    toggleButton.textContent = this.showAllEvents ? 'Show Recent Events' : 'Show All Events';
                }
            },

            renderFullTimeEvents: function() {
                this.addKickoffCommentary();
                this.addHalftimeCommentary();
                this.addFulltimeCommentary();
                this.renderCommentary();
            },

            updateStats: function(update) {
                if (update.home_team.possession !== undefined && update.away_team.possession !== undefined) {
                    const homePossessionBar = document.querySelector('.stat-item:nth-child(1) .stat-bar.home-bar');
                    const awayPossessionBar = document.querySelector('.stat-item:nth-child(1) .stat-bar.away-bar');

                    if (homePossessionBar) homePossessionBar.style.width = `${update.home_team.possession}%`;
                    if (awayPossessionBar) awayPossessionBar.style.width = `${update.away_team.possession}%`;

                    const homePossessionValue = document.querySelector('.stat-item:nth-child(1) .home-value');
                    const awayPossessionValue = document.querySelector('.stat-item:nth-child(1) .away-value');

                    if (homePossessionValue) homePossessionValue.textContent = `${update.home_team.possession}%`;
                    if (awayPossessionValue) awayPossessionValue.textContent = `${update.away_team.possession}%`;
                }

                if (update.home_team.shots !== undefined && update.away_team.shots !== undefined) {
                    const homeShotsValue = document.querySelector('.stat-item:nth-child(2) .home-value');
                    const awayShotsValue = document.querySelector('.stat-item:nth-child(2) .away-value');

                    if (homeShotsValue) homeShotsValue.textContent = update.home_team.shots;
                    if (awayShotsValue) awayShotsValue.textContent = update.away_team.shots;
                }

                if (update.home_team.shots_on_target !== undefined && update.away_team.shots_on_target !== undefined) {
                    const homeShotsOnTargetValue = document.querySelector('.stat-item:nth-child(3) .home-value');
                    const awayShotsOnTargetValue = document.querySelector('.stat-item:nth-child(3) .away-value');

                    if (homeShotsOnTargetValue) homeShotsOnTargetValue.textContent = update.home_team.shots_on_target;
                    if (awayShotsOnTargetValue) awayShotsOnTargetValue.textContent = update.away_team.shots_on_target;
                }
            },

            startMinuteUpdater: function() {
                if (this.minuteUpdateTimer) {
                    clearInterval(this.minuteUpdateTimer);
                }

                this.minuteUpdateTimer = setInterval(() => {
                    if (this.isLive && this.currentMinute < 90) {
                        const minuteCounter = document.querySelector('.minute-counter');
                        if (minuteCounter) {
                            const displayedMinute = parseInt(minuteCounter.textContent) || 0;

                            if (displayedMinute < this.currentMinute) {
                                minuteCounter.textContent = `${displayedMinute + 1}'`;
                            }
                        }
                    } else {
                        clearInterval(this.minuteUpdateTimer);
                    }
                }, 2000);
            },

            displayEvent: function(event, shouldScroll = true) {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                console.log(`Displaying event: ${event.type} at minute ${event.minute}`);

                const eventId = `commentary-${event.type}-${event.minute}-${event.main_player_id || 'none'}`;

                if (feed.querySelector(`#${eventId}`)) {
                    console.log(`Event ${eventId} already displayed, skipping`);
                    return;
                }

                const item = document.createElement('div');
                item.className = `commentary-item ${event.type} ${event.team}-team`;

                if (event.type === 'GOAL' || event.type === 'RED_CARD' || event.type === 'YELLOW_CARD') {
                    item.classList.add('key-event');
                }

                if (shouldScroll) {
                    item.classList.add('new-event');
                }

                item.id = eventId;

                item.innerHTML = `
                <div class="event-minute">${event.minute}'</div>
                <div class="event-icon">${this.getEventIcon(event.type)}</div>
                <div class="event-content">
                    <div class="event-text">${event.commentary}</div>
                    ${event.type === 'GOAL' ? this.formatGoalDetails(event) : ''}
                </div>
            `;

                if (this.isLive && shouldScroll) {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 10);
                }

                feed.appendChild(item);

                if (shouldScroll) {
                    this.scrollToBottom(feed);

                    setTimeout(() => {
                        item.classList.remove('new-event');
                    }, 2000);

                    if (event.type === 'GOAL' && window.Audio) {
                        try {
                            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            const oscillator = audioContext.createOscillator();
                            const gainNode = audioContext.createGain();

                            oscillator.type = 'sine';
                            oscillator.frequency.value = 523.25;
                            gainNode.gain.value = 0.1;

                            oscillator.connect(gainNode);
                            gainNode.connect(audioContext.destination);

                            oscillator.start(0);
                            oscillator.stop(audioContext.currentTime + 0.2);
                        } catch(e) {
                            console.log("Audio notification failed:", e);
                        }
                    }
                }
            },

            scrollToBottom: function(element) {
                setTimeout(() => {
                    element.scrollTop = element.scrollHeight;

                    setTimeout(() => {
                        if (element.scrollTop + element.clientHeight < element.scrollHeight) {
                            element.scrollTop = element.scrollHeight;
                        }
                    }, 100);
                }, 10);
            },

            getEventIcon: function(type) {
                switch(type) {
                    case 'GOAL': return '<i class="bi bi-bullseye"></i>';
                    case 'SHOT_ON_TARGET': return '<i class="bi bi-record-circle"></i>';
                    case 'SHOT_OFF_TARGET': return '<i class="bi bi-record-circle-fill"></i>';
                    case 'CORNER': return '<i class="bi bi-flag-fill"></i>';
                    case 'YELLOW_CARD': return '<div class="card yellow-card"></div>';
                    case 'RED_CARD': return '<div class="card red-card"></div>';
                    case 'GREAT_SAVE': return '<i class="bi bi-hand-thumbs-up"></i>';
                    default: return '<i class="bi bi-circle"></i>';
                }
            },

            formatGoalDetails: function(event) {
                return `
                <div class="goal-scorer">
                    <strong>${event.main_player_name || 'Player'}</strong>
                    ${event.secondary_player_name ?
                    `<span class="assist-label">(assist: ${event.secondary_player_name})</span>` : ''}
                </div>
                <div class="goal-score">
                    ${event.home_score || 0} - ${event.away_score || 0}
                </div>
            `;
            },

            addKickoffCommentary: function() {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                const existingKickoff = Array.from(feed.querySelectorAll('.commentary-item'))
                    .some(item => item.textContent.includes('kicks off'));

                if (existingKickoff) return;

                const homeTeamName = document.querySelector('.home-team .team-name').textContent;
                const awayTeamName = document.querySelector('.away-team .team-name').textContent;

                const kickoffEvent = {
                    type: 'GENERIC',
                    minute: 0,
                    team: 'neutral',
                    commentary: `The match between ${homeTeamName} and ${awayTeamName} kicks off!`
                };

                this.displayEvent(kickoffEvent);

                this.displayedEvents.push(kickoffEvent);
            },

            addHalftimeCommentary: function() {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                const existingHalftime = Array.from(feed.querySelectorAll('.commentary-item'))
                    .some(item => item.textContent.includes('Half time!'));

                if (existingHalftime) return;

                const halftimeEvent = {
                    type: 'GENERIC',
                    minute: 45,
                    team: 'neutral',
                    commentary: "Half time! The players head to the dressing rooms."
                };

                this.displayEvent(halftimeEvent);

                this.displayedEvents.push(halftimeEvent);
            },

            addFulltimeCommentary: function() {
                const feed = document.getElementById('js-commentary-feed');
                if (!feed) return;

                const existingFulltime = Array.from(feed.querySelectorAll('.commentary-item'))
                    .some(item => item.textContent.includes('Full time!'));

                if (existingFulltime) return;

                const fulltimeEvent = {
                    type: 'GENERIC',
                    minute: 90,
                    team: 'neutral',
                    commentary: "Full time! The match ends."
                };

                this.displayEvent(fulltimeEvent);

                this.displayedEvents.push(fulltimeEvent);
            },

            endMatch: function() {
                this.isLive = false;

                this.addFulltimeCommentary();

                this.showAllEvents = true;

                while (this.pendingEvents.length > 0) {
                    const event = this.pendingEvents.shift();
                    this.displayEvent(event, true);
                }

                const liveIndicator = document.querySelector('.live-indicator');
                if (liveIndicator) {
                    liveIndicator.textContent = 'FULL TIME';
                    liveIndicator.className = 'finished-indicator';
                }

                if (this.minuteUpdateTimer) {
                    clearInterval(this.minuteUpdateTimer);
                    this.minuteUpdateTimer = null;
                }

                if (this.eventDisplayTimer) {
                    clearInterval(this.eventDisplayTimer);
                    this.eventDisplayTimer = null;
                }
            }
        };

        matchViewer.init();

        const ensureVisibility = function() {
            document.querySelectorAll('.commentary-item').forEach(item => {
                item.style.display = 'flex';
                item.style.opacity = '1';

                const content = item.querySelector('.event-content');
                if (content) content.style.display = 'block';
            });

            const minuteCounter = document.querySelector('.minute-counter');
            if (minuteCounter) minuteCounter.style.display = 'inline-block';
        };

        setInterval(ensureVisibility, 2000);

        const observer = new MutationObserver(ensureVisibility);
        observer.observe(document.getElementById('js-commentary-feed') || document, {
            childList: true,
            subtree: true
        });
    });
</script>
