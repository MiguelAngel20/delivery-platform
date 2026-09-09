<?php

namespace App\Enums;

enum LoyaltyRewardType: string
{
    case LaunchFifty = 'launch_fifty';
    case StreakReward = 'streak_reward';

    public function label(): string
    {
        return match ($this) {
            self::LaunchFifty => 'Bienvenida 50% servicio',
            self::StreakReward => 'Recompensa por pedidos',
        };
    }
}
