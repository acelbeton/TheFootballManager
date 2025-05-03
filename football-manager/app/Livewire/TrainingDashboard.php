<?php

namespace App\Livewire;

use AllowDynamicProperties;
use App\Http\Enums\TrainingType;
use App\Models\TrainingSession;
use App\PlayerStatistics;
use App\Services\TrainingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Livewire;

class TrainingDashboard extends Component
{
    public $selectedPlayers = [];
    public $hasTrainedTeamToday = false;
    public $hasTrainedIndividualToday = false;
    public $trainingHistory;
    public $players;
    public $statisticNames;
//    public $lastTrainingResults;

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
            ->get(['id', 'type', 'participants', 'created_at']); // Explicitly select needed columns
    }

    public function updateCount($count)
    {
        // This is just a dummy method to handle the event
    }

    public function trainTeam()
    {
        if ($this->hasTrainedTeamToday) return;

        TrainingService::trainTeam();
        $this->hasTrainedTeamToday = true;

        $this->players = $this->players->fresh();
        $this->refreshTrainingHistory();

        session()->flash('team-training', 'Team training completed!');
    }

    public function trainIndividuals()
    {
        $this->validate();

        if ($this->hasTrainedIndividualToday) return;

        TrainingService::trainPlayer($this->selectedPlayers);
        $this->hasTrainedIndividualToday = true;

        $this->players = $this->players->fresh();
        $this->refreshTrainingHistory();
        $this->reset('selectedPlayers');

        session()->flash('individual-training', 'Individual training completed!');
    }

    public function mount()
    {
        $team = Auth::user()->currentTeam;
        $this->selectedPlayers = [];

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
