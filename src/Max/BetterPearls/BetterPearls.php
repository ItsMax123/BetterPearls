<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use Max\BetterPearls\addons\scorehud\ScoreHudListener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use WeakMap;

class BetterPearls extends PluginBase {
    private static BetterPearls $instance;

    /**
     * @var WeakMap<Player, Session>
     */
    private WeakMap $sessions;

    private Config $messages;
    private int $cooldown;
    private bool $refundCanceled;
    private bool $cancelLaunchSuffocation;
    private bool $cancelLandingInsideBlock;
    private bool $cancelLandingSmallArea;
    private bool $cancelLandingSuffocating;

    public function onLoad(): void {
        self::$instance = $this;
    }

    public function onEnable(): void {
        $this->sessions = new WeakMap();

        $this->saveDefaultConfig();
        $config = $this->getConfig();
        $this->cooldown = is_int($cooldown = $config->get("cooldown", 300)) ? max($cooldown, 1) : 300;
        $this->refundCanceled = is_bool($refundCanceled = $config->get("refund-canceled", true)) ? $refundCanceled : true;
        $this->cancelLaunchSuffocation = is_bool($cancelLaunchSuffocation = $config->get("cancel-launch-suffocating", true)) ? $cancelLaunchSuffocation : true;
        $this->cancelLandingInsideBlock = is_bool($cancelLandingInsideBlock = $config->get("cancel-landing-inside-block", true)) ? $cancelLandingInsideBlock : true;
        $this->cancelLandingSmallArea = is_bool($cancelLandingSmallArea = $config->get("cancel-landing-small-area")) ? $cancelLandingSmallArea : false;
        $this->cancelLandingSuffocating = is_bool($cancelLandingSuffocating = $config->get("cancel-landing-suffocating")) ? $cancelLandingSuffocating : false;

        $this->saveResource("messages.yml");
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        if ($this->getServer()->getPluginManager()->getPlugin("ScoreHud") !== null) {
            $this->getServer()->getPluginManager()->registerEvents(new ScoreHudListener(), $this);
        }
    }

    public static function getInstance(): BetterPearls {
        return self::$instance;
    }

    public function getSession(Player $player): Session {
        return $this->sessions[$player] ??= new Session();
    }

    public function getMessage(string $message): string {
        return TextFormat::colorize($this->messages->get($message, $message));
    }

    public function getCooldown(): int {
        return $this->cooldown;
    }

    public function getRefundCanceled(): bool {
        return $this->refundCanceled;
    }

    public function getCancelLaunchSuffocation(): bool {
        return $this->cancelLaunchSuffocation;
    }

    public function getCancelLandingInsideBlock(): bool {
        return $this->cancelLandingInsideBlock;
    }

    public function getCancelLandingSmallArea(): bool {
        return $this->cancelLandingSmallArea;
    }

    public function getCancelLandingSuffocating(): bool {
        return $this->cancelLandingSuffocating;
    }
}