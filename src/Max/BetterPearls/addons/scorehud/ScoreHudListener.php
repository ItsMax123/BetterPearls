<?php

declare(strict_types=1);

namespace Max\BetterPearls\addons\scorehud;

use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\TagsResolveEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use Max\BetterPearls\BetterPearls;
use Max\BetterPearls\events\PearlCooldownStartEvent;
use Max\BetterPearls\events\PearlCooldownStopEvent;
use pocketmine\event\Listener;

final class ScoreHudListener implements Listener {
    public function onTagResolve(TagsResolveEvent $event): void {
        $tag = $event->getTag();
        if ($tag->getName() === "betterpearls.cooldown") {
            $tag->setValue("0");
        }
    }

    /**
     * @priority MONITOR
     */
    public function onPearlCooldownStart(PearlCooldownStartEvent $event): void {
        (new PlayerTagUpdateEvent($event->getPlayer(), new ScoreTag("betterpearls.cooldown", (string)($event->getCooldown()/20))))->call();
        BetterPearls::getInstance()->getScheduler()->scheduleDelayedRepeatingTask(new ScoreHudTask($event->getPlayer()), ($offset = $event->getCooldown()%20) === 0 ? 20 : $offset, 20);
    }

    /**
     * @priority MONITOR
     */
    public function onPearlCooldownStop(PearlCooldownStopEvent $event): void {
        (new PlayerTagUpdateEvent($event->getPlayer(), new ScoreTag("betterpearls.cooldown", "0")))->call();
    }
}