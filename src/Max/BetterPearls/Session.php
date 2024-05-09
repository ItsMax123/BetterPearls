<?php

namespace Max\BetterPearls;

use pocketmine\Server;

class Session {
    private int $lastPearlLaunch;

    public function __construct() {
        $this->stopPearlCooldown();
    }

    public function startPearlCooldown(): void {
        $this->lastPearlLaunch = Server::getInstance()->getTick();
    }

    public function stopPearlCooldown(): void {
        $this->lastPearlLaunch = -BetterPearls::getInstance()->getConfig()->get("cooldown", 300);
    }

    public function hasPearlCooldown(): bool {
        return $this->lastPearlLaunch + BetterPearls::getInstance()->getConfig()->get("cooldown", 300) > Server::getInstance()->getTick();
    }

    public function getPearlCooldownExpiry(): int {
        return $this->lastPearlLaunch + BetterPearls::getInstance()->getConfig()->get("cooldown", 300) - Server::getInstance()->getTick();
    }
}