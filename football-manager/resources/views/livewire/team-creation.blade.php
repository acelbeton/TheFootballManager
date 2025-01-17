<div>
    <h2>Create Your Team</h2>

    @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="createTeam">
        <div>
            <label for="name">Team Name:</label>
            <input type="text" id="name" wire:model="name" />
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="league">Select League:</label>
            <select id="league" wire:model="league_id">
                <option value="">-- Choose a League --</option>
                @foreach ($leagues as $league)
                    <option value="{{ $league->id }}">{{ $league->name }}</option>
                @endforeach
            </select>
            @error('league_id') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button type="submit">Create Team</button>
    </form>
</div>
