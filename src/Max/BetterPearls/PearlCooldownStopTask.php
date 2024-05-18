<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use Max\BetterPearls\events\PearlCooldownStopEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class PearlCooldownStopTask extends Task {
    private Player $player;

    public function __construct(Player $player) {
        $this->player = $player;
    }

    public function onRun(): void {
        if ($this->player->isConnected()) {
            (new PearlCooldownStopEvent($this->player))->call();
            $this->player->sendMessage(TextFormat::colorize(BetterPearls::getInstance()->getConfig()->getNested("messages.cooldown-stop", "cooldown-stop")));
        }
    }
}