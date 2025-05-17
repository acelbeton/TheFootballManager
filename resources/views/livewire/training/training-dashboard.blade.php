@php
    use App\Http\Enums\TrainingType;
@endphp

<div class="container mx-auto p-4">
    <div class="training-dashboard">
        <div class="training-section team-training">
            @include('livewire.training.partials.team-training-card')
        </div>

        <div class="training-section history">
            <div class="card h-100">
                <div class="card-header bg-highlight text-white">
                    Training History
                </div>
                <div class="card-body overflow-auto" style="max-height: 300px;">
                    <div x-data="{ animate: false }"
                         x-init="Livewire.on('history-updated', () => {
                             animate = true;
                             setTimeout(() => animate = false, 500);
                         })"
                         class="border-0">
                        <ul class="list-group rounded training-history-list">
                            @foreach($trainingHistory as $session)
                                <li class="list-group-item border-start-0 border-end-0 {{ $loop->first ? 'border-top-0' : '' }} {{ $loop->last ? 'border-bottom-0' : '' }}"
                                    x-show.transition.opacity.duration.500ms="!animate"
                                    x-transition:enter.delay.100ms>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small>{{ $session->created_at->format('M d, H:i') }}</small>
                                            <br>
                                            <span class="badge bg-{{ $session->type === TrainingType::TEAM->name ? 'primary' : 'warning' }}">
                                                {{ ucfirst($session->type) }}
                                            </span>
                                        </div>
                                        @if ($session->type === TrainingType::INDIVIDUAL->name)
                                            <span class="badge bg-secondary">
                                                @foreach($session->playerParticipants as $participant)
                                                    {{ $participant->name }} @if(!$loop->last),@endif
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="individual-training-section">
        @include('livewire.training.partials.individual-training-card')
    </div>
</div>
