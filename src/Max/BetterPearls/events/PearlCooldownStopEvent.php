<?php

declare(strict_types=1);

namespace Max\BetterPearls\events;

use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class PearlCooldownStopEvent extends PlayerEvent {
    public function __construct(Player $player) {
        $this->player = $player;
    }
}