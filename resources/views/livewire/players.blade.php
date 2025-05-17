<div>
    <h2>Player Management</h2>

    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="addPlayer">
        <input type="text" wire:model="name" placeholder="Player Name" required />
        <select wire:model="team_id">
            <option value="">Free Agent</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}">{{ $team->name }}</option>
            @endforeach
        </select>
        <input type="text" wire:model="position" placeholder="Position" required />
        <input type="number" wire:model="market_value" placeholder="Market Value" required />
        <button type="submit">Add Player</button>
    </form>

    <ul>
        @foreach($players as $player)
            <li>
                {{ $player->name }} - {{ $player->position }} ({{ $player->market_value }})
                @if ($player->team)
                    - {{ $player->team->name }}
                @else
                    - Free Agent
                @endif
                <button wire:click="deletePlayer({{ $player->id }})">Delete</button>
            </li>
        @endforeach
    </ul>
</div>
