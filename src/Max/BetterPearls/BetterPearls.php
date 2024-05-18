<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use Max\BetterPearls\addons\scorehud\ScoreHudListener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class BetterPearls extends PluginBase {
    private static BetterPearls $instance;

    /** @var Session[] */
    private array $sessions = [];

    public function onLoad(): void {
        self::$instance = $this;
    }

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        if ($this->getServer()->getPluginManager()->getPlugin("ScoreHud") !== null) {
            $this->getServer()->getPluginManager()->registerEvents(new ScoreHudListener(), $this);
        }
    }

    public static function getInstance(): BetterPearls {
        return self::$instance;
    }

    public function getSession(Player $player): Session {
        return $this->sessions[$player->getUniqueId()->getBytes()] ??= new Session();
    }

    public function removeSession(Player $player): void {
        unset($this->sessions[$player->getUniqueId()->getBytes()]);
    }

    public function getCooldown(): int {
        return $this->getConfig()->get("cooldown", 300);
    }
}