<?php

namespace ryun42680\auctionhouse\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ryun42680\auctionhouse\AuctionHouseLoader;
use ryun42680\lib\itemparser\ItemParser;

final class AuctionManagementCommand extends Command {

    public function __construct() {
        parent::__construct('거래소관리', '거래소를 관리합니다.');
        $this->setPermission(DefaultPermissions::ROOT_OPERATOR);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if ($sender instanceof Player) {
            if ($this->testPermission($sender)) {
                switch (array_shift($args) ?? '') {
                    case '물품제거':
                        AuctionHouseLoader::getInstance()->sendAuctionHouse($sender, null, true);
                        break;

                    case '가격':
                        $minPrice = array_shift($args);
                        $maxPrice = array_shift($args);
                        if (is_numeric($minPrice) and is_numeric($maxPrice)) {
                            $item = $sender->getInventory()->getItemInHand();
                            if (!$item->isNull()) {
                                settype($minPrice, 'integer');
                                settype($maxPrice, 'integer');
                                AuctionHouseLoader::$standard [ItemParser::toString($item->setCount(1))] = [$minPrice, $maxPrice];
                                $sender->sendMessage(AuctionHouseLoader::$prefix . $item->getName() . '§r§7 의 기준가를 설정했습니다 (' . $minPrice . ' / ' . $maxPrice . ')');
                            } else {
                                $sender->sendMessage(AuctionHouseLoader::$prefix . '공기의 가격은 설정할 수 없습니다.');
                            }
                        } else {
                            $sender->sendMessage(AuctionHouseLoader::$prefix . '/거래소관리 가격 [최저가] [최대가] - 가격 기준을 설정합니다.');
                        }
                        break;

                    case '금지품목':
                        $item = $sender->getInventory()->getItemInHand();
                        if (!$item->isNull()) {
                            AuctionHouseLoader::$ban [] = ItemParser::toString($item->setCount(1));
                            $sender->sendMessage(AuctionHouseLoader::$prefix . $item->getName() . '§r§7을(를) 제한했습니다.');
                        } else {
                            $sender->sendMessage(AuctionHouseLoader::$prefix . '공기는 제한할 수 없습니다.');
                        }
                        break;

                    default:
                        $sender->sendMessage(AuctionHouseLoader::$prefix . '/거래소관리 물품제거 - 물품을 강제 제거합니다. (반환 X)');
                        $sender->sendMessage(AuctionHouseLoader::$prefix . '/거래소관리 가격 [최저가] [최대가] - 가격 기준을 설정합니다.');
                        $sender->sendMessage(AuctionHouseLoader::$prefix . '/거래소관리 금지품목 - 손에 든 아이템을 금지품목 설정합니다.');
                }
            }
        }
    }
}