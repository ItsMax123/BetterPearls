<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use pocketmine\Server;

class Session {
    private int $endPearlCooldown;
    private int $lastPearlLand;

    public function __construct() {
        $this->stopPearlCooldown();
        $this->setPearlLand();
    }

    public function startPearlCooldown(int $cooldown): void {
        $this->endPearlCooldown = Server::getInstance()->getTick() + $cooldown;
    }

    public function stopPearlCooldown(): void {
        $this->endPearlCooldown = Server::getInstance()->getTick();
    }

    public function hasPearlCooldown(): bool {
        return $this->endPearlCooldown > Server::getInstance()->getTick();
    }

    public function getPearlCooldownExpiry(): int {
        return $this->endPearlCooldown - Server::getInstance()->getTick();
    }

    public function setPearlLand(): void {
        $this->lastPearlLand = Server::getInstance()->getTick();
    }

    public function hasPearlLandingNow(): bool {
        return $this->lastPearlLand === Server::getInstance()->getTick();
    }
}