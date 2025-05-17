<div class="container d-flex justify-content-center align-items-center mt-5">
    <div class="auth-card">
        <h2 class="auth-card-title">Create Your Team</h2>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <form wire:submit="createTeam">
            <div class="input-group mb-3">
                <input type="text" wire:model="name" id="name" class="input" required>
                <label for="name" class="input-label">Team Name</label>
                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
            </div>

            <x-custom-select
                label="Select League"
                name="league"
                :options="$leagues->pluck('name', 'id')->toArray()"
                wire:model="selectedLeagueId"
            />
            <button type="submit" class="button button-primary">Create Team</button>
        </form>
    </div>
</div>
