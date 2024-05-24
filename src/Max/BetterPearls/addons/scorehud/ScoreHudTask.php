<?php

declare(strict_types=1);

namespace Max\BetterPearls\addons\scorehud;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use Max\BetterPearls\BetterPearls;
use Max\BetterPearls\Session;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class ScoreHudTask extends Task {
    private Player $player;
    private Session $session;

    public function __construct(Player $player) {
        $this->player = $player;
        $this->session = BetterPearls::getInstance()->getSession($this->player);
    }

    public function onRun(): void {
        if ($this->player->isConnected()) {
            (new PlayerTagUpdateEvent($this->player, new ScoreTag("betterpearls.cooldown", (string)($this->session->getPearlCooldownExpiry()/20))))->call();

            // The last update will be taken care of by the PearlCooldownStopEvent listener.
            // This makes sure that the task ends even if the cooldown is not a multiple of 20 ticks.
            if ($this->session->getPearlCooldownExpiry() > 20) return;
        }
        $this->getHandler()->cancel();
    }
}