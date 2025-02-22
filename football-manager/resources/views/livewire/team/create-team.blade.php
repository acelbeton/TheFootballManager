<div class="container mt-5">
    <h2>Create Your Team</h2>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit="createTeam">
        <div class="mb-3">
            <label for="name" class="form-label">Team Name</label>
            <input type="text" wire:model="name" id="name" class="form-control" required>
            @error('name') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <div class="mb-3">
            <label for="league" class="form-label">Select League</label>
            <select wire:model="selectedLeagueId" id="league" class="form-control" required>
                <option value="" selected disabled>Select a league</option>
                @foreach ($leagues as $league)
                    <option value="{{ $league->id }}">{{ $league->name }}</option>
                @endforeach
            </select>
            @error('league') <span class="text-danger">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Create Team</button>
    </form>
</div>
