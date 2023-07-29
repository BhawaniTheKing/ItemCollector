<?php
declare(strict_types=1);

namespace BhawaniTheKing;

use Closure;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ReflectionException;
use tobias14\autopickup\utils\Configuration;

class Main extends PluginBase implements Listener
{

    /** @var Configuration $configuration */
    private Configuration $configuration;

    public function onEnable() : void
    {
        $this->reloadConfig();
        $this->initConfiguration();

        $pluginMgr = $this->getServer()->getPluginManager();
        try {
            $onBreak = Closure::fromCallable([$this, 'onBreak']);
            $pluginMgr->registerEvent(BlockBreakEvent::class, $onBreak, EventPriority::HIGHEST, $this);
            $onEntityDeath = Closure::fromCallable([$this, 'onEntityDeath']);
            $pluginMgr->registerEvent(EntityDeathEvent::class, $onEntityDeath, EventPriority::HIGHEST, $this);
        } catch (ReflectionException $e) {
            $this->getLogger()->critical($e->getMessage());
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    public function onBreak(BlockBreakEvent $event) : void 
    {
        $player = $event->getPlayer();
        if(!$this->shouldPickup($player->getWorld()->getFolderName())) {
            return;
        }

        // Send Items To Player.
        $drops = $event->getDrops();
        foreach ($drops as $key => $drop) {
            if($player->getInventory()->canAddItem($drop)) {
                $player->getInventory()->addItem($drop);
                unset($drops[$key]);
                continue;
            }
            if($this->configuration->fullInvPopup != '') {
                $player->sendPopup(TextFormat::colorize($this->configuration->fullInvPopup));
            }
        }
        $event->setDrops($drops);

        // Send EXP To Player.
        $xpDrops = $event->getXpDropAmount();
        $player->getXpManager()->addXp($xpDrops);
        $event->setXpDropAmount(0);
    }

    public function onEntityDeath(EntityDeathEvent $event): void
    {
        $entity = $event->getEntity();
        if(!$this->shouldPickup($entity->getWorld()->getFolderName())) {
            return;
        }
        $lastDamageEvent = $entity->getLastDamageCause();
        if(!($lastDamageEvent instanceof EntityDamageByEntityEvent)) {
            return;
        }
        $player = $lastDamageEvent->getDamager();
        if(!($player instanceof Player)) {
            return;
        }

        // Send Items To Player.
        $drops = $event->getDrops();
        foreach ($drops as $key => $drop) {
            if($player->getInventory()->canAddItem($drop)) {
                $player->getInventory()->addItem($drop);
                unset($drops[$key]);
                continue;
            }
            if($this->configuration->fullInvPopup != '') {
                $player->sendPopup(TextFormat::colorize($this->configuration->fullInvPopup));
            }
        }
        $event->setDrops($drops);

        // Send EXP To Player.
        $xpDrops = $event->getXpDropAmount();
        $player->getXpManager()->addXp($xpDrops);
        $event->setXpDropAmount(0);
    }

    /**
     * @param string $world
     * @return bool
     */
    private function shouldPickup(string $world): bool
    {
        $mode = strtolower($this->configuration->mode);
        $affectedWorlds = $this->configuration->affectedWorlds;

        return ($mode === 'blacklist' && !in_array($world, $affectedWorlds)) or
            ($mode === 'whitelist' && in_array($world, $affectedWorlds));
    }

    private function initConfiguration(): void
    {
        $config = $this->getConfig();
        $this->configuration = new Configuration();
        $this->configuration->fullInvPopup = $config->get('Full_Inventory_PoPUP', '');
        $this->configuration->mode = $config->get('Mode', 'blacklist');
        $this->configuration->affectedWorlds = $config->get('Worlds', []);
    }
}
