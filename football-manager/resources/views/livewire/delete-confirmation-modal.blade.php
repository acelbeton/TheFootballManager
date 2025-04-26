<div>
    <!-- Remove duplicate overlay -->
    <div
        class="modal @if($show) d-block @endif"
        tabindex="-1"
        role="dialog"
        style="display: none;" {{-- Fallback for initial state --}}
        wire:keydown.escape="closeModal"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Team</h5>
                </div>

                <div class="modal-body">
                    <p>Are you sure you want to delete <strong>{{ $teamName }}</strong>?</p>
                    <p>This action cannot be undone.</p>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        wire:click="closeModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="btn btn-danger"
                        wire:click="delete"
                    >
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Livewire-controlled backdrop -->
    @if($show)
        <div class="modal-backdrop fade show" wire:click="closeModal"></div>
    @endif
</div>
