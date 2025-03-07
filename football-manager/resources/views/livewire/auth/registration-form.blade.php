 <div class="d-flex justify-content-center align-items-center m-5">
     <div class="auth-card">
         <h2 class="auth-card-title">Register</h2>
         <form wire:submit="register">
             @csrf
             <div class="input-group mb-3">
                 <input type="text" class="input" wire:model="name" name="name" id="name" required>
                 <label for="name" class="input-label">Name</label>
                 @error('name') <span class="error">{{ $message }}</span> @enderror
             </div>

             <div class="input-group mb-3">
                 <input type="email" class="input" wire:model="email" name="email" id="email" required>
                 <label for="email" class="input-label">Email</label>
                 @error('email') <span class="error">{{ $message }}</span> @enderror
             </div>

             <div class="input-group mb-3">
                 <input type="password" class="input" wire:model="password" name="password" id="password" required>
                 <label for="password" class="input-label">Password</label>
                 @error('password') <span class="error">{{ $message }}</span> @enderror
             </div>

             <div class="input-group mt-4">
                 <input type="password" class="input" id="password_confirmation" wire:model="password_confirmation">
                 <label for="password_confirmation" class="input-label">Confirm Password</label>
                 @error('password_confirmation') <span class="error">{{ $message }}</span> @enderror
             </div>

             <button type="submit" class="button button-primary w-100 mt-3">Register</button>
         </form>
     </div>
</div>
