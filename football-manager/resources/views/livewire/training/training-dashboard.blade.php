@php
    use App\Http\Enums\TrainingType;
@endphp

<div class="container mx-auto p-4">
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            @include('livewire.training.partials.team-training-card')
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    Training History
                </div>
                <div class="card-body overflow-auto" style="max-height: 300px;">
                    <ul class="list-group list-group-flush">
                        @foreach($trainingHistory as $session) {{-- TODO add event on change --}}
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small>{{ $session->created_at->format('M d, H:i') }}</small>
                                    <br>
                                    <span class="badge bg-{{ $session->type === TrainingType::TEAM->name ? 'primary' : 'warning' }}">
                                        {{ ucfirst($session->type) }}
                                    </span>
                                </div>
                                @if ($session->type === TrainingType::INDIVIDUAL->name)
                                    <span class="badge bg-secondary">
                                        @foreach($session->playerParticipants as $participants)
                                                <span>{{ $participants->name }},</span>  {{-- TODO design/layout change --}}
                                        @endforeach
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div>
        @include('livewire.training.partials.individual-training-card')
    </div>
</div>
