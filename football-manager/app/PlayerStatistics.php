<?php

namespace App;

enum PlayerStatistics: string
{
    case ATTACKING = 'attacking';
    case DEFENDING = 'defending';
    case STAMINA = 'stamina';
    case TECHNICAL_SKILLS = 'technical_skills';
    case SPEED = 'speed';
    case TACTICAL_SENSE = 'tactical_sense';

    public function label(): string
    {
        return match($this) {
            self::ATTACKING => 'Attacking',
            self::DEFENDING => 'Defending',
            self::STAMINA => 'Stamina',
            self::TECHNICAL_SKILLS => 'Technical Skills',
            self::SPEED => 'Speed',
            self::TACTICAL_SENSE => 'Tactical Sense',
        };
    }
}
