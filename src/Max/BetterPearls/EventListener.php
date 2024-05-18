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
use pocketmine\math\AxisAlignedBB;
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
        } else {
            $pearlCooldownStartEvent = new PearlCooldownStartEvent($player, $this->plugin->getCooldown());
            $pearlCooldownStartEvent->call();
            if (!$pearlCooldownStartEvent->isCancelled()) {
                $session->startPearlCooldown($pearlCooldownStartEvent->getCooldown());
                $player->getNetworkSession()->sendDataPacket(PlayerStartItemCooldownPacket::create('ender_pearl', $pearlCooldownStartEvent->getCooldown()));
                $this->plugin->getScheduler()->scheduleDelayedTask(new PearlCooldownStopTask($player), $pearlCooldownStartEvent->getCooldown());
                $player->sendMessage(TextFormat::colorize($this->plugin->getConfig()->getNested("messages.cooldown-start", "cooldown-start")));
            }
        }
    }

    public function onPearlLand(ProjectileHitEvent $event): void {
        if (!$event->getEntity() instanceof EnderPearlProjectile) return;
        $player = $event->getEntity()->getOwningEntity();
        if (!$player instanceof Player) return;
        $this->plugin->getSession($player)->setPearlLand();
    }

    private function isInCollisionBox(World $world, float $x, float $y, float $z): ?AxisAlignedBB {
        foreach ([[$x, $y, $z], [$x, $y + 1, $z], [$x, $y - 1, $z], [$x + 1, $y, $z], [$x - 1, $y, $z], [$x, $y, $z + 1], [$x, $y, $z - 1]] as [$nx, $ny, $nz]) {
            foreach ($world->getBlockAt((int)floor($nx), (int)floor($ny), (int)floor($nz))->getCollisionBoxes() as $box) {
                if ($box->minX <= $x && $x <= $box->maxX &&
                    $box->minY <= $y && $y <= $box->maxY &&
                    $box->minZ <= $z && $z <= $box->maxZ) {
                    return $box;
                }
            }
        }
        return null;
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

        // This whole section of code does not look very clean can probably be optimized.
        // However, everything here does have a purpose.
        // Therefore, don't so don't start deleting things in the name of optimization/refactoring.

        // When pearling into a block above a fence from below, the pearl might land inside the block.
        // In these the cases, set the $y coord 0.05m underneath the block.
        foreach ($world->getBlockAt((int)floor($x), (int)floor($y), (int)floor($z))->getCollisionBoxes() as $box) {
            if ($box->minX < $x && $x < $box->maxX &&
                $box->minY < $y && $y < $box->maxY &&
                $box->minZ < $z && $z < $box->maxZ) {
                $y = $box->minY - 0.05;
            }
        }

        // If the position is still touching or inside a collision box:
        //  Look 0.05m in each direction for positions that are not inside collision boxes.
        if ($this->isInCollisionBox($world, $x, $y, $z)) {
            $newX = $x;
            $newY = $y;
            $newZ = $z;
            foreach ([[0.05, 0, 0], [-0.05, 0, 0], [0, 0.05, 0], [0, -0.05, 0], [0, 0, 0.05], [0, 0, -0.05]] as [$tempX, $tempY, $tempZ]) {
                if (!$this->isInCollisionBox($world, $x + $tempX, $y + $tempY, $z + $tempZ)) {
                    $newX += $tempX;
                    $newY += $tempY;
                    $newZ += $tempZ;
                }
            }
            $x = $newX;
            $y = $newY;
            $z = $newZ;
        }

        // Honestly I've never had this happen in practice, but there's probably a way.
        // If the previous step failed to find a position that is not inside a collision box:
        //  Stop here and possibly cancel the event.
        if ($this->isInCollisionBox($world, $x, $y, $z)) {
            if ($config->get("cancel-landing-inside-block", true)) {
                $event->cancel();
                $player->sendMessage(TextFormat::colorize($config->getNested("messages.cancel-landing-inside-block", "cancel-landing-inside-block")));
            }
            return;
        }

        // Get the nearest collision boxes within 1m in each of the XY directions to try to set a better position.
        // If there is less than 0.6m of available space:
        //  Possibly cancel event depending on config.
        // If there is between 0.6m and 1m of space:
        //  Set the new position to be in the middle of this available space.
        // If the is more than 1m of space but one of the collision boxes is less than 0.5m away:
        //  Set the position to be 0.5m away from that wall.
        $maxX = $x;
        $minX = $x;
        for ($n = 0; $n < 20; $n++) {
            $maxX += 0.05;
            $box = $this->isInCollisionBox($world, $maxX, $y, $z);
            if ($box !== null) {
                $maxX = $box->minX;
                break;
            }
        }
        for ($n = 0; $n < 20; $n++) {
            $minX -= 0.05;
            $box = $this->isInCollisionBox($world, $minX, $y, $z);
            if ($box !== null) {
                $minX = $box->maxX;
                break;
            }
        }
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

        $maxZ = $z;
        $minZ = $z;
        for ($n = 0; $n < 20; $n++) {
            $maxZ += 0.05;
            $box = $this->isInCollisionBox($world, $x, $y, $maxZ);
            if ($box !== null) {
                $maxZ = $box->minZ;
                break;
            }
        }
        for ($n = 0; $n < 20; $n++) {
            $minZ -= 0.05;
            $box = $this->isInCollisionBox($world, $x, $y, $minZ);
            if ($box !== null) {
                $minZ = $box->maxZ;
                break;
            }
        }
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

        // Bring the Y coord down 1.75 blocks or until it runs into a collision box.
        for ($n = 0; $n < 35; $n++) {
            $y -= 0.05;
            $box = $this->isInCollisionBox($world, $x, $y, $z);
            if ($box !== null) {
                $y = $box->maxY;
                break;
            }
        }

        if ($config->get("cancel-landing-suffocating") && $this->isInCollisionBox($world, $x, $y + $player->getEyeHeight(), $z)) {
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