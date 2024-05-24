<?php

declare(strict_types=1);

namespace Max\BetterPearls;

use Max\BetterPearls\events\PearlCooldownStartEvent;
use Max\BetterPearls\events\PearlCooldownStopEvent;
use pocketmine\entity\projectile\EnderPearl as EnderPearlProjectile;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\EnderPearl as EnderPearlItem;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlayerStartItemCooldownPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

final class EventListener implements Listener {
    private BetterPearls $plugin;

    public function __construct(BetterPearls $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @priority HIGHEST
     */
    public function onUse(PlayerItemUseEvent $event): void {
        if (!$event->getItem() instanceof EnderPearlItem) return;
        $player = $event->getPlayer();
        $config = $this->plugin->getConfig();
        if ($config->get("cancel-launch-suffocating", true)) {
            $position = $player->getPosition();
            if ($this->isInCollisionBox($position->getWorld(), $position->x, $position->y + $player->getEyeHeight(), $position->z)) {
                $event->cancel();
                $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-launch-suffocating", "cancel-launch-suffocating")));
                return;
            }
        }
        $session = $this->plugin->getSession($player);
        if ($session->hasPearlCooldown()) {
            $event->cancel();
            $player->sendMessage(str_replace(
                ["{TIME}"],
                [ceil($session->getPearlCooldownExpiry() / 20)],
                TextFormat::colorize($config->getNested("messages.cancel-launch-cooldown", "cancel-launch-cooldown"))
            ));
            return;
        }
        $pearlCooldownStartEvent = new PearlCooldownStartEvent($player, $this->plugin->getCooldown());
        $pearlCooldownStartEvent->call();
        if (!$pearlCooldownStartEvent->isCancelled()) {
            $session->startPearlCooldown($pearlCooldownStartEvent->getCooldown());
            $this->plugin->getScheduler()->scheduleDelayedTask(new PearlCooldownStopTask($player), $pearlCooldownStartEvent->getCooldown());
            $player->getNetworkSession()->sendDataPacket(PlayerStartItemCooldownPacket::create('ender_pearl', $pearlCooldownStartEvent->getCooldown()));
            $player->sendMessage(TextFormat::colorize($this->plugin->getConfig()->getNested("messages.cooldown-start", "cooldown-start")));
        }
    }

    public function onPearlLand(ProjectileHitEvent $event): void {
        if (!$event->getEntity() instanceof EnderPearlProjectile) return;
        $player = $event->getEntity()->getOwningEntity();
        if (!$player instanceof Player) return;
        $this->plugin->getSession($player)->setPearlLand();
    }

    /**
     * Checks if the given XYZ position is in a block's collision box.
     */
    private function isInCollisionBox(World $world, float $x, float $y, float $z): bool {
        foreach ([[$x, $y, $z], [$x, $y + 1, $z], [$x, $y - 1, $z], [$x + 1, $y, $z], [$x - 1, $y, $z], [$x, $y, $z + 1], [$x, $y, $z - 1]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                if ($box->minX <= $x && $x <= $box->maxX &&
                    $box->minY <= $y && $y <= $box->maxY &&
                    $box->minZ <= $z && $z <= $box->maxZ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @priority LOWEST
     */
    public function onTeleportBefore(EntityTeleportEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player || !$this->plugin->getSession($player)->hasPearlLandingNow()) return;
        $to = $event->getTo();
        $world = $to->getWorld();
        $x = $to->getX();
        $y = $to->getY();
        $z = $to->getZ();
        $config = $this->plugin->getConfig();

        // When pearling into a block that is above a fence, from below, the pearl might land inside the block.
        // If the position is inside a collision box:
        //  Set the Y coord 0.05m underneath the block.
        foreach ($world->getBlockAt((int)floor($x), (int)floor($y), (int)floor($z))->getCollisionBoxes() as $box) {
            if ($box->minX < $x && $x < $box->maxX &&
                $box->minY < $y && $y < $box->maxY &&
                $box->minZ < $z && $z < $box->maxZ) {
                $y = $box->minY - 0.05;
            }
        }

        // If the position is still inside or at least touching a collision box:
        //  Look 0.05m in each direction for positions that are not inside/touching collision boxes.
        if ($this->isInCollisionBox($world, $x, $y, $z)) {
            $new = $x;
            foreach ([0.05, -0.05] as $temp) {
                if (!$this->isInCollisionBox($world, $x + $temp, $y, $z)) {
                    $new += $temp;
                }
            }
            $x = $new;
            $new = $z;
            foreach ([0.05, -0.05] as $temp) {
                if (!$this->isInCollisionBox($world, $x, $y, $z + $temp)) {
                    $new += $temp;
                }
            }
            $z = $new;
            $new = $y;
            foreach ([0.05, -0.05] as $temp) {
                if (!$this->isInCollisionBox($world, $x, $y + $temp, $z)) {
                    $new += $temp;
                }
            }
            $y = $new;
        }

        // If the previous step failed to find a position that is not inside/touching a collision box:
        //  Stop here and possibly cancel the teleportation depending on the config.
        if ($this->isInCollisionBox($world, $x, $y, $z)) {
            if ($config->get("cancel-landing-inside-block", true)) {
                $event->cancel();
                $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-landing-inside-block", "cancel-landing-inside-block")));
            }
            return;
        }

        // Get the nearest collision boxes on the X axis, in both directions, within 1m of the original position.
        $initialPosition = new Vector3($x, $y, $z);
        $minX = $x - 1;
        $maxX = $x + 1;

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x + 1, $y, $z);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x + 1, $y, $z], [$x + 1, $y - 1, $z]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithXValue($finalPosition, $box->minX);
                if ($nextHit === null || !$box->isVectorInYZ($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $maxX = $nextHit->x;
            }
        }

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x - 1, $y, $z);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x - 1, $y, $z], [$x - 1, $y - 1, $z]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithXValue($finalPosition, $box->maxX);
                if ($nextHit === null || !$box->isVectorInYZ($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $minX = $nextHit->x;
            }
        }

        // If there is less than 0.6m of available space:
        //  Possibly cancel the teleportation depending on the config.
        // If there is between 0.6m and 1.0m of available space:
        //  Set the new position to be in the middle of this available space.
        // If there is more than 1.0m of available space but one of the collision boxes is less than 0.5m away:
        //  Set the position to be 0.5m away from that wall.
        if ($maxX - $minX >= 0.6) {
            if ($maxX - $minX <= 1) {
                $x = ($maxX + $minX) / 2;
            } elseif ($maxX - $x < 0.5) {
                $x = $maxX - 0.5;
            } elseif ($x - $minX < 0.5) {
                $x = $minX + 0.5;
            }
        } elseif ($config->get("cancel-landing-small-area")) {
            $event->cancel();
            $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-landing-small-area", "cancel-landing-small-area")));
            return;
        }

        // Get the nearest collision boxes on the Z axis, in both directions, within 1m of the original position.
        $initialPosition = new Vector3($x, $y, $z);
        $maxZ = $z + 1;
        $minZ = $z - 1;

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x, $y, $z + 1);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x, $y, $z + 1], [$x, $y - 1, $z + 1]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithZValue($finalPosition, $box->minZ);
                if ($nextHit === null || !$box->isVectorInXY($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $maxZ = $nextHit->z;
            }
        }

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x, $y, $z - 1);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x, $y, $z - 1], [$x, $y - 1, $z - 1]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithZValue($finalPosition, $box->maxZ);
                if ($nextHit === null || !$box->isVectorInXY($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $minZ = $nextHit->z;
            }
        }

        // If there is less than 0.6m of available space:
        //  Possibly cancel the teleportation depending on the config.
        // If there is between 0.6m and 1.0m of available space:
        //  Set the new position to be in the middle of this available space.
        // If there is more than 1.0m of available space but one of the collision boxes is less than 0.5m away:
        //  Set the position to be 0.5m away from that wall.
        if ($maxZ - $minZ >= 0.6) {
            if ($maxZ - $minZ <= 1) {
                $z = ($maxZ + $minZ) / 2;
            } elseif ($maxZ - $z < 0.5) {
                $z = $maxZ - 0.5;
            } elseif ($z - $minZ < 0.5) {
                $z = $minZ + 0.5;
            }
        } elseif ($config->get("cancel-landing-small-area")) {
            $event->cancel();
            $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-landing-small-area", "cancel-landing-small-area")));
            return;
        }

        // Get the nearest collision boxes on the Y axis, in both directions, within 1.8m of the original position.
        $initialPosition = new Vector3($x, $y, $z);
        $maxY = $y + 2;
        $minY = $y - 2;

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x, $y + 1.8, $z);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x, $y + 1, $z], [$x, $y + 2, $z]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithYValue($finalPosition, $box->minY);
                if ($nextHit === null || !$box->isVectorInXZ($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $maxY = $nextHit->y;
            }
        }

        $distance = PHP_INT_MAX;
        $finalPosition = new Vector3($x, $y - 1.8, $z);
        foreach ([[$x, $y, $z], [$x, $y - 1, $z], [$x, $y - 2, $z], [$x, $y - 3, $z]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                $nextHit = $initialPosition->getIntermediateWithYValue($finalPosition, $box->maxY);
                if ($nextHit === null || !$box->isVectorInXZ($nextHit) || $distance <= ($tempDistance = $initialPosition->distanceSquared($nextHit))) continue;
                $distance = $tempDistance;
                $minY = $nextHit->y;
            }
        }

        // If there is less than 1.62m of available space:
        //  Possibly cancel the teleportation depending on the config.
        // If there is more than 1.62m of available space but the top collision boxes is less than 1.8m away:
        //  Set the position to be 1.8m under the that ceiling.
        if ($maxY - $minY >= 1.62) {
            if ($maxY - $y < 1.8) {
                $y = $maxY - 1.8;
            }
        } elseif ($config->get("cancel-landing-suffocating")) {
            $event->cancel();
            $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-landing-suffocating", "cancel-landing-suffocating")));
            return;
        }

        // Set new teleport position
        $event->setTo(new Position($x, $y, $z, $world));
    }

    /**
     * @priority MONITOR
     * @handleCancelled
     */
    public function onTeleportAfter(EntityTeleportEvent $event): void {
        if (!$event->isCancelled()) return;
        $player = $event->getEntity();
        if (!$player instanceof Player) return;
        $session = $this->plugin->getSession($player);
        if (!$session->hasPearlLandingNow()) return;
        (new PearlCooldownStopEvent($player))->call();
        $session->stopPearlCooldown();
        if ($this->plugin->getConfig()->get("refund-canceled", true)) {
            $player->getInventory()->addItem(VanillaItems::ENDER_PEARL());
        }
    }

    public function onDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        (new PearlCooldownStopEvent($player))->call();
        $this->plugin->getSession($player)->stopPearlCooldown();
    }

    public function onLeave(PlayerQuitEvent $event): void {
        $this->plugin->removeSession($event->getPlayer());
    }
}