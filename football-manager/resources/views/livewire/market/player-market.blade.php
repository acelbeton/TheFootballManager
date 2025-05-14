@php
    use App\Http\Enums\PlayerPosition;
@endphp

<div class="market-container">
    <div class="dashboard-header section-card">
        <div class="header-content">
            <h1>Player Market</h1>
            <div class="team-meta">
                <div class="team-budget">
                    <span class="label">Budget:</span>
                    <span class="value">{{ number_format($teamBudget) }} €</span>
                </div>
            </div>
        </div>
    </div>

    <div class="market-filters">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <input type="text" wire:model.live="search" class="input" placeholder="Search by name...">
                    <label for="search" class="input-label">Search Player</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <x-custom-select
                        label="Position"
                        name="position"
                        :options="$positions"
                        wire:model.live="position"
                    />
                </div>
            </div>
            <div class="col-md-6">
                <div class="range-input-group">
                    <span class="range-label">Rating Range</span>
                    <div class="range-container">
                        <input type="range" wire:model.live="minRating" min="0" max="100" class="form-range">
                        <span class="range-values">{{ $minRating }} - {{ $maxRating }}</span>
                        <input type="range" wire:model.live="maxRating" min="0" max="100" class="form-range">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="market-list">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th wire:click="sortBy('name')" class="sortable">
                            Player Name
                            @if ($sortField === 'name')
                                <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </th>
                        <th wire:click="sortBy('position')" class="sortable">
                            Position
                            @if ($sortField === 'position')
                                <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </th>
                        <th wire:click="sortBy('rating')" class="sortable">
                            Rating
                            @if ($sortField === 'rating')
                                <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </th>
                        <th wire:click="sortBy('market_value')" class="sortable">
                            Market value
                            @if ($sortField === 'market_value')
                                <i class="bi bi-arrow-{{ $sortDirection === 'asc' ? 'up' : 'down' }}"></i>
                            @endif
                        </th>
                        <th>Current Bid</th>
                        <th>Time Left</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($players as $player)
                        <tr>
                            <td>{{ $player->name }}</td>
                            <td>
                                <span class="position-badge {{ strtolower(str_replace('_', '-', $player->position)) }}">
                                    {{ PlayerPosition::getName($player->position) }}
                                </span>
                            </td>
                            <td>
                                <div class="rating-stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        @if ($i <= floor($player->rating / 20))
                                            <i class="bi bi-star-fill"></i>
                                        @elseif ($i - 0.5 <= $player->rating / 20)
                                            <i class="bi bi-star-half"></i>
                                        @else
                                            <i class="bi bi-star"></i>
                                        @endif
                                    @endfor
                                    <span class="rating-number">{{ $player->rating }}</span>
                                </div>
                            </td>
                            <td>{{ number_format($player->market_value) }} €</td>
                            <td>
                                @if (isset($marketInputs[$player->getKey()]) && $marketInputs[$player->getKey()]->count() > 0)
                                    {{ number_format($marketInputs[$player->getKey()]->first()->current_bid_amount) }} €
                                    <span class="bid-count">{{ $marketInputs[$player->getKey()]->count() }} bids</span>
                                @else
                                    <span class="no-bids">No bids yet</span>
                                @endif
                            </td>
                            <td>
                                @if (isset($marketInputs[$player->getKey()]) && $marketInputs[$player->getKey()]->count() > 0)
                                    <div x-data="{
                                        endTime: '{{ $marketInputs[$player->id]->first()->bidding_end_date }}',
                                        remaining: '',
                                        init() {
                                            this.calculateTimeLeft();
                                            setInterval(() => this.calculateTimeLeft(), 1000);
                                        },
                                        calculateTimeLeft() {
                                            const end = new Date(this.endTime).getTime();
                                            const now = new Date().getTime();
                                            const distance = end - now;

                                            if (distance < 0) {
                                                this.remaining = 'Bidding ended';
                                                return;
                                            }

                                            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                                            this.remaining = `${hours}h ${minutes}m ${seconds}s`;
                                        }
                                    }" x-text="remaining" class="time-left"></div>
                                @else
                                    <span>-</span>
                                @endif
                            </td>
                            <td>
                                {{-- TODO button size --}}
                                <button class="button button-primary button-small" wire:click="selectPlayer({{ $player->getKey() }})">
                                    Place Bid
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No players available on the market</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination-container">
            {{ $players->links() }}
        </div>
    </div>

    @if($showBidModal && $selectedPlayer)
        @include('livewire.market.partials.show-bid-modal')
    @endif

    <!-- Notification Toast -->
    <!-- TODO modularize -->
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
