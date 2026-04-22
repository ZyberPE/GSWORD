<?php

declare(strict_types=1);

namespace GSWORD;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use pocketmine\item\VanillaItems;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\EnchantmentInstance;

class Main extends PluginBase {

    private Config $cooldowns;

    public function onEnable(): void {
        $this->saveDefaultConfig();

        // Load cooldown storage
        @mkdir($this->getDataFolder());
        $this->cooldowns = new Config($this->getDataFolder() . "cooldowns.yml", Config::YAML);
    }

    public function onDisable(): void {
        $this->cooldowns->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) return true;

        if(!$sender->hasPermission("gsword.use")){
            $sender->sendMessage($this->color($this->getConfig()->getNested("messages.no-permission")));
            return true;
        }

        $cooldownTime = (int)$this->getConfig()->get("cooldown");
        $playerName = strtolower($sender->getName());
        $currentTime = time();

        $lastUse = $this->cooldowns->get($playerName, 0);
        $remaining = ($lastUse + $cooldownTime) - $currentTime;

        if($remaining > 0){
            $msg = str_replace("{time}", (string)$remaining, $this->getConfig()->getNested("messages.cooldown"));
            $sender->sendMessage($this->color($msg));
            return true;
        }

        // Save new cooldown
        $this->cooldowns->set($playerName, $currentTime);
        $this->cooldowns->save();

        // Create sword
        $sword = VanillaItems::DIAMOND_SWORD();

        // Name
        $sword->setCustomName($this->color($this->getConfig()->getNested("sword.name")));

        // Lore
        $lore = [];
        foreach($this->getConfig()->getNested("sword.lore") as $line){
            $lore[] = $this->color($line);
        }

        $enchantLore = [];
        $enchants = $this->getConfig()->getNested("sword.enchants");

        foreach($enchants as $name => $level){
            $enchant = $this->getEnchant($name);
            if($enchant !== null){
                $sword->addEnchantment(new EnchantmentInstance($enchant, (int)$level));

                if($this->getConfig()->getNested("sword.show-enchants-in-lore")){
                    $enchantLore[] = "§7" . ucfirst(str_replace("_", " ", $name)) . " " . $level;
                }
            }
        }

        if($this->getConfig()->getNested("sword.show-enchants-in-lore")){
            $lore[] = "§r";
            $lore = array_merge($lore, $enchantLore);
        }

        $sword->setLore($lore);

        // Give item
        $sender->getInventory()->addItem($sword);

        $sender->sendMessage($this->color($this->getConfig()->getNested("messages.received")));

        return true;
    }

    private function getEnchant(string $name){
        return match(strtolower($name)){
            "sharpness" => VanillaEnchantments::SHARPNESS(),
            "unbreaking" => VanillaEnchantments::UNBREAKING(),
            "fire_aspect" => VanillaEnchantments::FIRE_ASPECT(),
            "efficiency" => VanillaEnchantments::EFFICIENCY(),
            "fortune" => VanillaEnchantments::FORTUNE(),
            "looting" => VanillaEnchantments::LOOTING(),
            default => null
        };
    }

    private function color(string $text): string {
        return str_replace("&", "§", $text);
    }
}
