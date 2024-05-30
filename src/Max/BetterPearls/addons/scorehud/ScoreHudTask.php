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
        if ($this->player->isConnected() && $this->session->getPearlCooldownTimeLeft() > 0) {
            (new PlayerTagUpdateEvent($this->player, new ScoreTag("betterpearls.cooldown", (string)($this->session->getPearlCooldownTimeLeft() / 20))))->call();
        } else {
            $this->getHandler()->cancel();
        }
    }
}