<?php

declare(strict_types=1);

namespace Max\BetterPearls\events;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class PearlCooldownStartEvent extends PlayerEvent implements Cancellable {
    use CancellableTrait;

    private int $cooldown;

    public function __construct(Player $player, int $cooldown) {
        $this->player = $player;
        $this->cooldown = $cooldown;
    }

    public function getCooldown(): int {
        return $this->cooldown;
    }

    public function setCooldown(int $cooldown): void {
        $this->cooldown = $cooldown;
    }
}