<div>
    <h2>Team Management</h2>

    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="addTeam">
        <input type="text" wire:model="name" placeholder="Team Name" required />
        <input type="number" wire:model="budget" placeholder="Budget" required />
        <input type="text" wire:model="current_tactic" placeholder="Tactic" required />
        <button type="submit">Add Team</button>
    </form>

    <ul>
        @foreach($teams as $team)
            <li>
                {{ $team->name }} - {{ $team->budget }} ({{ $team->current_tactic }})
                <button wire:click="deleteTeam({{ $team->id }})">Delete</button>
            </li>
        @endforeach
    </ul>
</div>
