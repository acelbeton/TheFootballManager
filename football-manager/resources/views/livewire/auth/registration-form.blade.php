<div class="container mt-5">
    <h2>Register</h2>

    <form wire:submit="register">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" wire:model="name" name="name" id="name" required>
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" wire:model="email" name="email" id="email" required>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" wire:model="password" name="password" id="password" required>
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="mt-4">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" id="password_confirmation" wire:model="password_confirmation">
            @error('password_confirmation') <span class="error">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</div>

