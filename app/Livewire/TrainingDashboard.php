<?php

namespace App\Livewire;

use App\Http\Enums\PlayerStatistics;
use App\Http\Enums\TrainingType;
use App\Services\TrainingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Training')]
class TrainingDashboard extends Component
{
    public $selectedPlayers = [];
    public $hasTrainedTeamToday = false;
    public $hasTrainedIndividualToday = false;
    public $trainingHistory;
    public $players;
    public $statisticNames;
    public $trainingResults = [];

    protected $rules = [
        'selectedPlayers' => 'max:2',
        'selectedPlayers.*' => 'exists:players,id'
    ];

    protected $listeners = [
        'updateSelectedPlayersCount' => 'updateCount',
        'refreshHistory' => 'refreshTrainingHistory'
    ];

    protected function refreshTrainingHistory()
    {
        $this->trainingHistory = Auth::user()->currentTeam
            ->trainingSession()
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'participants', 'created_at']);
    }

    public function updateCount($count)
    {
        // This is just a dummy method to handle the event
    }

    public function trainTeam()
    {
        if ($this->hasTrainedTeamToday) return;

        $this->trainingResults = TrainingService::trainTeam();
        $this->hasTrainedTeamToday = true;

        $this->players = $this->players->fresh();
        $this->refreshTrainingHistory();

        $this->dispatch('training-completed');
    }

    public function trainIndividuals()
    {
        $this->validate();

        if ($this->hasTrainedIndividualToday) return;

        $this->trainingResults = TrainingService::trainPlayer($this->selectedPlayers);
        $this->hasTrainedIndividualToday = true;

        $this->players = $this->players->fresh();
        $this->refreshTrainingHistory();

        $this->dispatch('training-completed');
        $this->reset('selectedPlayers');
    }

    public function resetTrainingResults()
    {
        $this->trainingResults = [];
    }

    public function mount()
    {
        $team = Auth::user()->currentTeam;
        $this->selectedPlayers = [];
        $this->trainingResults = [];

        $this->players = $team->players()
            ->with('statistics')
            ->get();

        $this->hasTrainedTeamToday = $team->trainingSession()
            ->where('type', TrainingType::TEAM)
            ->whereDate('created_at', today())
            ->exists();

        $this->hasTrainedIndividualToday = $team->trainingSession()
            ->where('type', TrainingType::INDIVIDUAL)
            ->whereDate('created_at', today())
            ->exists();

        $this->trainingHistory = $team->trainingSession()
            ->latest()
            ->limit(5)
            ->get();

        $this->statisticNames = PlayerStatistics::cases();
    }

    public function render()
    {
        return view('livewire.training.training-dashboard')
            ->with([
                'nextTrainingTime' => now()->addDay()->startOfDay()->diffForHumans(),
            ]);
    }
}
