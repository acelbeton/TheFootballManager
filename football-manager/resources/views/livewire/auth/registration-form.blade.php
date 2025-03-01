<div class="container auth-form">
    <div class="d-flex justify-content-center align-items-center h-100">
        <form wire:submit="register">
            <h2>Register</h2>
            @csrf
            <div class="form-group mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" wire:model="name" name="name" id="name" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" wire:model="email" name="email" id="email" required>
                @error('email') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" wire:model="password" name="password" id="password" required>
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group mt-4">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" class="form-control" id="password_confirmation" wire:model="password_confirmation">
                @error('password_confirmation') <span class="error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
</div>

