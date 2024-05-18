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
            (new PlayerTagUpdateEvent($this->player, new ScoreTag("betterpearls.cooldown", (string)max(0, ceil($this->session->getPearlCooldownExpiry()/20)))))->call();
            if ($this->session->hasPearlCooldown()) return;
        }
        $this->getHandler()->cancel();
    }
}