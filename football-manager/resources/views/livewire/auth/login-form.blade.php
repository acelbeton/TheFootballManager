 <div class="d-flex justify-content-center align-items-center m-5">
    <div class="auth-card">
        <h2 class="auth-card-title">Login</h2>
        <form wire:submit="login">
            @csrf
            <div class="input-group mb-3">
                <input type="email" wire:model="email" name="email" id="email" class="input" required>
                <label for="email" class="input-label">Email</label>
                <div>
                    @error('email') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="input-group mb-3">
                <input type="password" wire:model="password" name="password" id="password" class="input" required>
                <label for="password" class="input-label">Password</label>
                <div>
                    @error('password') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            <button type="submit" class="button button-primary w-100">Login</button>
        </form>
    </div>
 </div>
