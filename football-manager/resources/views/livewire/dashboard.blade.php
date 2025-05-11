<div class="dashboard-container">
    @if(!$team)
        <div class="no-team-message">
            <div class="alert alert-info">
                <h4>You don't have a team yet</h4>
                <p>Create your first team to start your journey as a football manager.</p>
                <a href="{{ route('create-team') }}" class="button button-primary mt-3">Create Team</a>
            </div>
        </div>
    @else
        <div class="dashboard-header">
            <h1>{{ $team->name }} Dashboard</h1>
            <div class="team-meta">
                <div class="team-budget">
                    <span class="label">Budget:</span>
                    <span class="value">{{ number_format($team->team_budget) }} €</span>
                </div>
                <div class="team-rating">
                    <span class="label">Team Rating:</span>
                    <span class="value">{{ $team->team_rating }}/100</span>
                </div>
                <div class="team-tactic">
                    <span class="label">Current Tactic:</span>
                    <span class="value">{{ str_replace('_', ' ', $team->current_tactic) }}</span>
                </div>
            </div>
        </div>

        <div class="dashboard-stats-summary">
            <div class="position-badge position-{{ $teamPerformance['position'] }}">
                <span class="position-number">{{ $teamPerformance['position'] }}</span>
                <span class="position-label">{{ $teamPerformance['position'] == 1 ? 'st' : ($teamPerformance['position'] == 2 ? 'nd' : ($teamPerformance['position'] == 3 ? 'rd' : 'th')) }}</span>
            </div>
            <div class="stats-pills">
                <div class="stat-pill">
                    <span class="label">Played</span>
                    <span class="value">{{ $teamPerformance['matches_played'] }}</span>
                </div>
                <div class="stat-pill">
                    <span class="label">Won</span>
                    <span class="value">{{ $teamPerformance['matches_won'] }}</span>
                </div>
                <div class="stat-pill">
                    <span class="label">Drawn</span>
                    <span class="value">{{ $teamPerformance['matches_drawn'] }}</span>
                </div>
                <div class="stat-pill">
                    <span class="label">Lost</span>
                    <span class="value">{{ $teamPerformance['matches_lost'] }}</span>
                </div>
                <div class="stat-pill">
                    <span class="label">Goals</span>
                    <span class="value">{{ $teamPerformance['goals_scored'] }}:{{ $teamPerformance['goals_conceded'] }}</span>
                </div>
                <div class="stat-pill stat-pill-highlight">
                    <span class="label">Points</span>
                    <span class="value">{{ $teamPerformance['points'] }}</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="action-cards">
                <div class="row">
                    <div class="col-md-4">
                        <a href="{{ route('market') }}" class="card-link" wire:navigate>
                            <div class="card action-card">
                                <div class="card-body">
                                    <h3 class="card-title">Transfer Market</h3>
                                    <div class="card-icon">
                                        <i class="bi bi-currency-exchange"></i>
                                    </div>
                                    <div class="card-quick-stats">
                                        <div class="quick-stat">
                                            <span class="label">Available Players:</span>
                                            <span class="value">{{ $marketSummary['total_on_market'] }}</span>
                                        </div>
                                        <div class="quick-stat">
                                            <span class="label">Your Budget:</span>
                                            <span class="value">{{ number_format($marketSummary['team_budget']) }} €</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-4">
                        <a href="{{ route('team-training') }}" class="card-link" wire:navigate>
                            <div class="card action-card">
                                <div class="card-body">
                                    <h3 class="card-title">Team Training</h3>
                                    <div class="card-icon">
                                        <i class="bi bi-trophy"></i>
                                    </div>
                                    <div class="card-quick-stats">
                                        <div class="quick-stat">
                                            <span class="label">Squad Size:</span>
                                            <span class="value">{{ $rosterSummary['total'] }}</span>
                                        </div>
                                        <div class="quick-stat">
                                            <span class="label">Avg. Rating:</span>
                                            <span class="value">{{ round($rosterSummary['avg_rating']) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-md-4">
                        <a href="{{ route('change-team') }}" class="card-link" wire:navigate>
                            <div class="card action-card">
                                <div class="card-body">
                                    <h3 class="card-title">Team Management</h3>
                                    <div class="card-icon">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </div>
                                    <div class="card-text">
                                        <p>Manage your teams or create a new one (up to 3 teams)</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Matches</h3>
                        </div>
                        <div class="card-body">
                            @if($upcomingMatches->count() > 0)
                                <div class="match-list">
                                    @foreach($upcomingMatches as $match)
                                        <div class="match-item">
                                            <div class="match-date">
                                                {{ $match->match_date->format('d M, H:i') }}
                                            </div>
                                            <div class="match-teams">
                                                <div class="team home-team {{ $match->home_team_id == $team->getKey() ? 'your-team' : '' }}">
                                                    {{ $match->homeTeam->name }}
                                                </div>
                                                <div class="match-versus">vs</div>
                                                <div class="team away-team {{ $match->away_team_id == $team->getKey() ? 'your-team' : '' }}">
                                                    {{ $match->awayTeam->name }}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="card-footer text-center">
                                    <a href="#" class="view-all-link">View All Fixtures</a>
                                </div>
                            @else
                                <div class="no-data-message">
                                    <p>No upcoming matches scheduled</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">League Table</h3>
                        </div>
                        <div class="card-body">
                            @if($leagueStandings && $leagueStandings->count() > 0)
                                <div class="league-info">
                                    <div class="league-name">{{ $leagueInfo['name'] }}</div>
                                    <div class="season-dates">{{ $leagueInfo['start_date']->format('d M Y') }} - {{ $leagueInfo['end_date']->format('d M Y') }}</div>
                                </div>
                                <table class="standings-table">
                                    <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Team</th>
                                        <th>MP</th>
                                        <th>W</th>
                                        <th>D</th>
                                        <th>L</th>
                                        <th>GF:GA</th>
                                        <th>PTS</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($leagueStandings as $index => $standing)
                                        <tr class="{{ $standing->team_id == $team->getKey() ? 'your-team-row' : '' }}">
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $standing->team->name }}</td>
                                            <td>{{ $standing->matches_played }}</td>
                                            <td>{{ $standing->matches_won }}</td>
                                            <td>{{ $standing->matches_drawn }}</td>
                                            <td>{{ $standing->matches_lost }}</td>
                                            <td>{{ $standing->goals_scored }}:{{ $standing->goals_conceded }}</td>
                                            <td class="points-cell">{{ $standing->points }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                                <div class="card-footer text-center">
                                    <a href="#" class="view-all-link">View Full Table</a>
                                </div>
                            @else
                                <div class="no-data-message">
                                    <p>No league data available</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Squad Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="squad-summary">
                                        <div class="summary-stat">
                                            <span class="label">Total Players:</span>
                                            <span class="value">{{ $rosterSummary['total'] }}</span>
                                        </div>
                                        <div class="summary-stat">
                                            <span class="label">Average Rating:</span>
                                            <span class="value">{{ round($rosterSummary['avg_rating'], 1) }}</span>
                                        </div>
                                        <div class="summary-stat">
                                            <span class="label">Injured Players:</span>
                                            <span class="value">{{ $rosterSummary['injured'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="position-distribution">
                                        <h4>Position Distribution</h4>
                                        <div class="position-bars">
                                            @foreach($rosterSummary['by_position'] as $position => $count)
                                                <div class="position-bar-item">
                                                    <div class="position-name">{{ str_replace('_', ' ', $position) }}</div>
                                                    <div class="position-bar-container">
                                                        <div class="position-bar position-{{ strtolower(str_replace('_', '-', $position)) }}" style="width: {{ min(100, $count * 20) }}%">
                                                            <span class="position-count">{{ $count }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <a href="#" class="button button-primary">Manage Squad</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
