<?php

namespace App\Helpers;

use App\Http\Enums\PlayerPosition;

class StatsHelper
{
    public static function getStatsForPosition(PlayerPosition $position, ?int $qualityTier = null): array
    {
        $qualityTier = $qualityTier ?? self::determineQualityTier();

        $baseModifier = ($qualityTier - 3) * 8;

        $baseStats = self::getBaseStatsForPosition($position);

        $stats = [];
        foreach ($baseStats as $stat => $value) {
            $adjustedValue = $value + $baseModifier + rand(-5, 5);
            $stats[$stat] = max(1, min(100, $adjustedValue));
        }

        return $stats;
    }

    private static function determineQualityTier(): int
    {
        $roll = rand(1, 100);

        if ($roll <= 5) return 5;
        if ($roll <= 20) return 4;
        if ($roll <= 80) return 3;
        if ($roll <= 95) return 2;
        return 1;
    }

    private static function getBaseStatsForPosition(PlayerPosition $position): array
    {
        return match ($position) {
            PlayerPosition::GOALKEEPER => [
                'attacking'        => rand(10, 20),
                'defending'        => rand(55, 70),
                'stamina'          => rand(40, 55),
                'technical_skills' => rand(40, 55),
                'speed'            => rand(30, 45),
                'tactical_sense'   => rand(45, 60),
            ],
            PlayerPosition::CENTRE_BACK => [
                'attacking'        => rand(15, 30),
                'defending'        => rand(60, 75),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(35, 50),
                'speed'            => rand(40, 55),
                'tactical_sense'   => rand(50, 65),
            ],
            PlayerPosition::FULLBACK => [
                'attacking'        => rand(35, 50),
                'defending'        => rand(50, 65),
                'stamina'          => rand(60, 75),
                'technical_skills' => rand(45, 60),
                'speed'            => rand(60, 75),
                'tactical_sense'   => rand(45, 60),
            ],
            PlayerPosition::MIDFIELDER => [
                'attacking'        => rand(45, 60),
                'defending'        => rand(45, 60),
                'stamina'          => rand(55, 70),
                'technical_skills' => rand(55, 70),
                'speed'            => rand(45, 60),
                'tactical_sense'   => rand(55, 70),
            ],
            PlayerPosition::WINGER => [
                'attacking'        => rand(55, 70),
                'defending'        => rand(25, 40),
                'stamina'          => rand(55, 70),
                'technical_skills' => rand(60, 75),
                'speed'            => rand(65, 80),
                'tactical_sense'   => rand(40, 55),
            ],
            PlayerPosition::STRIKER => [
                'attacking'        => rand(65, 80),
                'defending'        => rand(20, 35),
                'stamina'          => rand(50, 65),
                'technical_skills' => rand(55, 70),
                'speed'            => rand(55, 70),
                'tactical_sense'   => rand(45, 60),
            ],
            default => [
                'attacking'        => 50,
                'defending'        => 50,
                'stamina'          => 50,
                'technical_skills' => 50,
                'speed'            => 50,
                'tactical_sense'   => 50,
            ],
        };
    }

    public static function calculateOverallRating(array $stats, PlayerPosition $position): int
    {
        $weights = self::getStatWeightsByPosition($position);
        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($weights as $stat => $weight) {
            if (isset($stats[$stat])) {
                $weightedSum += $stats[$stat] * $weight;
                $totalWeight += $weight;
            }
        }

        if ($totalWeight > 0) {
            return (int)round($weightedSum / $totalWeight);
        }

        return (int)round(array_sum($stats) / count($stats));
    }

    private static function getStatWeightsByPosition(PlayerPosition $position): array
    {
        return match ($position) {
            PlayerPosition::GOALKEEPER => [
                'attacking'        => 0.2,
                'defending'        => 2.5,
                'stamina'          => 0.8,
                'technical_skills' => 1.0,
                'speed'            => 0.5,
                'tactical_sense'   => 1.0,
            ],
            PlayerPosition::CENTRE_BACK => [
                'attacking'        => 0.3,
                'defending'        => 2.5,
                'stamina'          => 1.0,
                'technical_skills' => 0.8,
                'speed'            => 0.8,
                'tactical_sense'   => 1.1,
            ],
            PlayerPosition::FULLBACK => [
                'attacking'        => 0.8,
                'defending'        => 1.5,
                'stamina'          => 1.2,
                'technical_skills' => 1.0,
                'speed'            => 1.3,
                'tactical_sense'   => 0.7,
            ],
            PlayerPosition::MIDFIELDER => [
                'attacking'        => 1.0,
                'defending'        => 1.0,
                'stamina'          => 1.1,
                'technical_skills' => 1.4,
                'speed'            => 0.7,
                'tactical_sense'   => 1.3,
            ],
            PlayerPosition::WINGER => [
                'attacking'        => 1.4,
                'defending'        => 0.4,
                'stamina'          => 1.0,
                'technical_skills' => 1.2,
                'speed'            => 1.6,
                'tactical_sense'   => 0.9,
            ],
            PlayerPosition::STRIKER => [
                'attacking'        => 2.0,
                'defending'        => 0.3,
                'stamina'          => 0.8,
                'technical_skills' => 1.2,
                'speed'            => 1.1,
                'tactical_sense'   => 1.1,
            ],
            default => [
                'attacking'        => 1.0,
                'defending'        => 1.0,
                'stamina'          => 1.0,
                'technical_skills' => 1.0,
                'speed'            => 1.0,
                'tactical_sense'   => 1.0,
            ],
        };
    }

    public static function calculateMarketValue(int $rating, PlayerPosition $position): int
    {
        $positionMultiplier = match($position) {
            PlayerPosition::GOALKEEPER => 0.3,
            PlayerPosition::CENTRE_BACK => 0.4,
            PlayerPosition::FULLBACK => 0.5,
            PlayerPosition::MIDFIELDER => 0.55,
            PlayerPosition::WINGER => 0.7,
            PlayerPosition::STRIKER => 0.75,
            default => 1.0,
        };

        $baseValue = 1000 * pow(1.13, $rating - 40);
        $randomFactor = rand(90, 110) / 100;

        return (int)round($baseValue * $positionMultiplier * $randomFactor);
    }
}
