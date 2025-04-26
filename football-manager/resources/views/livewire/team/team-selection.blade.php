<div> <!-- Single root element wrapper -->
    <div class="container p-5">
        <div class="d-flex justify-content-evenly align-content-between">
            @foreach($teams as $team)
                @include('livewire.team.partials.team-card')
            @endforeach
        </div>

        @if ($teams->count() < 3)
            <div class="mt-5 d-flex justify-content-center">
                <a wire:navigate href="{{ route('create-team') }}"
                   id="createNewTeam"
                   class="button button-primary">
                    Create new Team
                </a>
            </div>
        @endif
    </div>

    <!-- Modal placed here (still within single root) -->
    <livewire:delete-confirmation-modal />
</div>
