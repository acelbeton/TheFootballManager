@php
    use App\Http\Enums\PlayerPosition;
@endphp

@if($showBidModal && $selectedPlayer)
    <div class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="custom-modal__header">
                <h5 class="custom-modal__title">Place Bid for {{ $selectedPlayer->name }}</h5>
                <button type="button" class="btn-close" wire:click="cancelBid"></button>
            </div>

            <div class="custom-modal__body">
                <div class="player-info mb-4">
                    <div class="d-flex align-items-center">
                        <div class="player-avatar">
                            <div class="position-icon {{ strtolower(str_replace('_', '-', $selectedPlayer->position)) }}">
                                {{ PlayerPosition::abbreviation($selectedPlayer->position) }}
                            </div>
                        </div>
                        <div class="ms-3">
                            <h4 class="mb-1">{{ $selectedPlayer->name }}</h4>
                            <div class="player-details">
                                <span class="badge bg-secondary">{{ PlayerPosition::getName($selectedPlayer->position) }}</span>
                                <span class="badge bg-info">Rating: {{ $selectedPlayer->rating }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="market-info mb-3">
                    <div class="row">
                        <div class="col-6">
                            <div class="info-label">Market Value</div>
                            <div class="info-value">{{ number_format($selectedPlayer->market_value) }} €</div>
                        </div>
                        <div class="col-6">
                            <div class="info-label">Your Budget</div>
                            <div class="info-value">{{ number_format($teamBudget) }} €</div>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <input type="number" wire:model="bidAmount" id="bidAmount" class="input" min="1">
                    <label for="bidAmount" class="input-label">Your Bid (€)</label>
                    @error('bidAmount') <span class="text-danger mt-1">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="custom-modal__footer">
                <button type="button" class="btn btn-secondary" wire:click="cancelBid">Cancel</button>
                <button type="button" class="btn btn-primary" wire:click="placeBid">Place Bid</button>
            </div>
        </div>
    </div>
@endif
