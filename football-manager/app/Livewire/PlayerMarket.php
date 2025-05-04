<?php

namespace App\Livewire;

use App\Events\BidPlaced;
use App\Http\Enums\PlayerPosition;
use App\Models\Market;
use App\Models\Player;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PlayerMarket extends Component
{
    use WithPagination;
    public $search = '';
    public $position = '';
    public $minRating = 0;
    public $maxRating = 100;
    public $sortField = 'market_value';
    public $sortDirection = 'desc';
    public $bidAmount = 0;
    public $selectedPlayer = null;
    public $showBidModal = false;

    protected $listeners = [
        'bidPlaced' => '$refresh',
        'echo:player-market,BidPlaced' => 'handleBidUpdate',
    ];

    protected $rules = [
        'bidAmount' => 'required|numeric|min:1',
    ];

    public function mount()
    {
        $this->selectedPlayer = null;
    }

    public function render()
    {
        $query = Player::query()
            ->where('is_on_market', true)
            ->where('is_injured', false);

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->position) {
            $query->where('position', $this->position);
        }

        $query->whereBetween('rating', [$this->minRating, $this->maxRating]);

        $players = $query->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        $playerIds = $players->pluck('id')->toArray();
        $marketInputs = Market::whereIn('player_id', $playerIds)
            ->orderBy('current_bid_amount', 'desc')
            ->get()
            ->groupBy('player_id');

        $userTeam = Auth::user()->currentTeam;
        $teamBudget = $userTeam ? $userTeam->team_budget : 0;

        return view('livewire.market.player-market', [
            'players' => $players,
            'marketInputs' => $marketInputs,
            'positions' => $this->getPositions(),
            'teamBudget' => $teamBudget,
        ]);
    }

    public function getPositions()
    {
        $positions = [];
        foreach (PlayerPosition::cases() as $position) {
            $positions[$position->value] = PlayerPosition::getName($position->name);
        }
        return $positions;
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function selectPlayer($playerId)
    {
        $this->selectedPlayer = Player::find($playerId);
        $this->showBidModal = true;

        $highestBid = Market::where('player_id', $playerId)
            ->orderBy('current_bid_amount', 'desc')
            ->first();

        $this->bidAmount = $highestBid ?
            $highestBid->current_bid_amount + 100 :
            $this->selectedPlayer->market_value;
    }

    public function placeBid()
    {
        $this->validate();

        $user = Auth::user();
        $userTeam = $user->currentTeam;

        if ($userTeam->team_budget < $this->bidAmount) {
            $this->addError('bidAmount', 'Not enough funds');
            return;
        }

        $highestBid = Market::where('player_id', $this->selectedPlayer->id)
            ->orderBy('current_bid_amount', 'desc')
            ->first();

        $minimumBid = $highestBid ?
            $highestBid->current_bid_amount + 100 :
            $this->selectedPlayer->market_value;

        if ($this->bidAmount > $minimumBid) {
            $this->addError('bidAmount', 'Your bid must be at least ' . $minimumBid);
            return;
        }

        $marketInput = Market::updateOrCreate(
            [
                'player_id' => $this->selectedPlayer->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'current_bid_amount' => $this->bidAmount,
                'bidding_end_date' => Carbon::now()->addDays(1), // TODO maybe change
            ]
        );

        event(new BidPlaced($marketInput));

        $this->showBidModal = false;
        $this->reset(['bidAmount', 'selectedPlayer']);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Bid played successfully'
        ]);
    }

    public function handleBidUpdate()
    {
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'New bid placed on a player. Market has been updated'
        ]);
    }

    public function cancelBid()
    {
        $this->showBidModal = false;
        $this->reset(['bidAmount', 'selectedPlayer']);
    }
}
