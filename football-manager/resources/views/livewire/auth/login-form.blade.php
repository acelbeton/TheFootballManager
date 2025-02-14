 <div class="container mt-5">
    <h2>Login</h2>

    <form wire:submit="login">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" wire:model="email" name="email" id="email" class="form-control" required>
            <div>
                @error('email') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" wire:model="password" name="password" id="password" class="form-control" required>
            <div>
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Login</button>
    </form>
 </div>
