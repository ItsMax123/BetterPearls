<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use Max\BetterPearls\events\PearlCooldownStopEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class PearlCooldownStopTask extends Task {
    private Player $player;

    public function __construct(Player $player) {
        $this->player = $player;
    }

    public function onRun(): void {
        if ($this->player->isConnected() && BetterPearls::getInstance()->getSession($this->player)->getPearlCooldownTimeLeft() === 0) {
            (new PearlCooldownStopEvent($this->player))->call();
            $this->player->sendMessage(BetterPearls::getInstance()->getMessage("cooldown-stop"));
        }
    }
}