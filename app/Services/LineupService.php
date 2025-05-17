<?php

namespace App\Services;

use App\Http\Enums\PlayerPosition;
use App\Models\Formation;
use App\Models\LineupPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamLineup;
use Exception;

class LineupService
{
    /**
     * @throws Exception
     */
    public function createDefaultLineup(Team $team): TeamLineup
    {
        $formation = Formation::first();

        if (!$formation) {
            throw new Exception('No formations found in the database');
        }

        $lineup = TeamLineup::create([
            'team_id' => $team->getKey(),
            'match_id' => null,
            'formation_id' => $formation->getKey(),
            'tactic' => $team->current_tactic ?? 'DEFAULT_MODE',
        ]);

        $positions = $formation->positions;

        $formationToPlayerPosition = [
            'GOALKEEPER' => 'GOALKEEPER',
            'CENTRE_BACK_LEFT' => 'CENTRE_BACK',
            'CENTRE_BACK_RIGHT' => 'CENTRE_BACK',
            'CENTRE_BACK_CENTER' => 'CENTRE_BACK',
            'FULLBACK_LEFT' => 'FULLBACK',
            'FULLBACK_RIGHT' => 'FULLBACK',
            'MIDFIELDER_LEFT' => 'MIDFIELDER',
            'MIDFIELDER_RIGHT' => 'MIDFIELDER',
            'MIDFIELDER_CENTER' => 'MIDFIELDER',
            'MIDFIELDER_CENTER_LEFT' => 'MIDFIELDER',
            'MIDFIELDER_CENTER_RIGHT' => 'MIDFIELDER',
            'DEFENSIVE_MIDFIELDER_LEFT' => 'MIDFIELDER',
            'DEFENSIVE_MIDFIELDER_RIGHT' => 'MIDFIELDER',
            'ATTACKING_MIDFIELDER_CENTER' => 'MIDFIELDER',
            'ATTACKING_MIDFIELDER_LEFT' => 'WINGER',
            'ATTACKING_MIDFIELDER_RIGHT' => 'WINGER',
            'WINGER_LEFT' => 'WINGER',
            'WINGER_RIGHT' => 'WINGER',
            'STRIKER' => 'STRIKER',
            'STRIKER_LEFT' => 'STRIKER',
            'STRIKER_RIGHT' => 'STRIKER',
            'STRIKER_CENTER' => 'STRIKER',
        ];

        $positionPreferences = [
            'GOALKEEPER' => ['GOALKEEPER'],
            'CENTRE_BACK' => ['CENTRE_BACK_LEFT', 'CENTRE_BACK_RIGHT', 'CENTRE_BACK_CENTER'],
            'FULLBACK' => ['FULLBACK_LEFT', 'FULLBACK_RIGHT'],
            'MIDFIELDER' => ['MIDFIELDER_LEFT', 'MIDFIELDER_RIGHT', 'MIDFIELDER_CENTER', 'MIDFIELDER_CENTER_LEFT', 'MIDFIELDER_CENTER_RIGHT', 'DEFENSIVE_MIDFIELDER_LEFT', 'DEFENSIVE_MIDFIELDER_RIGHT', 'ATTACKING_MIDFIELDER_CENTER'],
            'WINGER' => ['WINGER_LEFT', 'WINGER_RIGHT', 'ATTACKING_MIDFIELDER_LEFT', 'ATTACKING_MIDFIELDER_RIGHT'],
            'STRIKER' => ['STRIKER', 'STRIKER_LEFT', 'STRIKER_RIGHT', 'STRIKER_CENTER'],
        ];

        $playersByPosition = [];
        foreach (PlayerPosition::cases() as $position) {
            $playersByPosition[$position->value] = Player::where('team_id', $team->getKey())
                ->where('position', $position->value)
                ->orderByDesc('rating')
                ->get();
        }

        $usedPlayerIds = [];
        $positionAssignments = [];
        foreach ($positions as $formationPosition => $coords) {
            $player = $this->findBestPlayerForPosition(
                $formationPosition,
                $positionPreferences,
                $playersByPosition,
                $usedPlayerIds
            );

            if ($player) {
                $positionAssignments[$formationPosition] = $player->getKey();
                $usedPlayerIds[] = $player->getKey();
            }
        }

        foreach ($positionAssignments as $formationPosition => $playerId) {
            $playerPositionType = $formationToPlayerPosition[$formationPosition] ?? 'BENCH';

            LineupPlayer::create([
                'lineup_id' => $lineup->getKey(),
                'player_id' => $playerId,
                'position' => $playerPositionType,
                'is_starter' => true,
                'position_order' => 0,
            ]);
        }

        $benchPlayers = Player::where('team_id', $team->getKey())
            ->whereNotIn('id', $usedPlayerIds)
            ->get();

        foreach ($benchPlayers as $player) {
            LineupPlayer::create([
                'lineup_id' => $lineup->getKey(),
                'player_id' => $player->getKey(),
                'position' => $player->position,
                'is_starter' => false,
                'position_order' => 0,
            ]);
        }

        return $lineup;
    }

    private function findBestPlayerForPosition(
        string $formationPosition,
        array $positionPreferences,
        array $playersByPosition,
        array $usedPlayerIds
    ): ?Player {
        $compatiblePositions = [];
        foreach ($positionPreferences as $playerPosition => $formationPositions) {
            if (in_array($formationPosition, $formationPositions)) {
                $compatiblePositions[] = $playerPosition;
            }
        }

        foreach ($compatiblePositions as $position) {
            foreach ($playersByPosition[$position] as $player) {
                if (!in_array($player->getKey(), $usedPlayerIds)) {
                    return $player;
                }
            }
        }

        foreach (PlayerPosition::cases() as $position) {
            foreach ($playersByPosition[$position->value] as $player) {
                if (!in_array($player->getKey(), $usedPlayerIds)) {
                    return $player;
                }
            }
        }

        return null;
    }
}
