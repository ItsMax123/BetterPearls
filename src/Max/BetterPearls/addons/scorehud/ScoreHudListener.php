<?php

declare(strict_types=1);

namespace Max\BetterPearls\addons\scorehud;

use Ifera\ScoreHud\event\TagsResolveEvent;
use Max\BetterPearls\BetterPearls;
use Max\BetterPearls\events\PearlCooldownStartEvent;
use pocketmine\event\Listener;

final class ScoreHudListener implements Listener {
    public function onTagResolve(TagsResolveEvent $event): void {
        $tag = $event->getTag();
        if ($tag->getName() === "betterpearls.cooldown") {
            $tag->setValue("0");
        }
    }

    public function onPearlCooldownStart(PearlCooldownStartEvent $event): void {
        BetterPearls::getInstance()->getScheduler()->scheduleRepeatingTask(new ScoreHudTask($event->getPlayer()), 20);
    }
}