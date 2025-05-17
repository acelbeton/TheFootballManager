<div>
    <div
        class="custom-modal-overlay"
        style="display: {{ $show ? 'flex' : 'none' }};"
        wire:keydown.escape="closeModal"
    >
        <div class="custom-modal">
            <div class="custom-modal__header">
                <h5 class="custom-modal__title">Delete Team</h5>
            </div>

            <div class="custom-modal__body">
                <p>Are you sure you want to delete <strong>{{ $teamName }}</strong>?</p>
                <p>This action cannot be undone.</p>
            </div>

            <div class="custom-modal__footer">
                <button
                    type="button"
                    class="button button-secondary"
                    wire:click="closeModal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="button button-warning"
                    wire:click="delete"
                >
                    Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>
