<?php

namespace ryun42680\auctionhouse\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ryun42680\auctionhouse\AuctionHouseLoader;
use ryun42680\auctionhouse\AuctionItem;
use ryun42680\lib\itemparser\ItemParser;

final class RegisterItemCommand extends Command {

    public function __construct() {
        parent::__construct('거래소등록', '거래소에 물품을 올립니다.');
        $this->setPermission(DefaultPermissions::ROOT_USER);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if ($sender instanceof Player) {
            if ($this->testPermission($sender)) {
                $price = array_shift($args);
                $count = array_shift($args);
                $time = array_shift($args);
                if (trim((string)$time) !== '') {
                    if (is_numeric($count) and is_numeric($time) and is_numeric($price)) {
                        $price = abs($price);
                        $inventory = $sender->getInventory();
                        $item = clone $inventory->getItemInHand();
                        if (!$item->isNull()) {
                            if ($item->getCount() >= $count and $count > 0) {
                                $itemId = ItemParser::toString($item->setCount(1));
                                if (!in_array($itemId, AuctionHouseLoader::$ban)) {
                                    $standard = AuctionHouseLoader::$standard [$itemId] ?? null;
                                    if (!is_array($standard) or ($standard [0] <= $price and $price <= $standard [1])) {
                                        if (1 <= $time and $time <= 72) {
                                            $time = (int)$time;
                                            $id = mt_rand(1111, PHP_INT_MAX);
                                            $item->setCount($count);
                                            AuctionHouseLoader::getInstance()->registerItem(new AuctionItem($id, $sender->getName(), intval($price), $item, time() + 60 * 60 * $time));
                                            $inventory->removeItem($item);
                                            $sender->sendMessage(AuctionHouseLoader::$prefix . '거래소에 아이템을 등록했습니다.');
                                            $sender->sendMessage(AuctionHouseLoader::$prefix . '등록된 아이템은 ' . $time . '시간 내로 팔리지 않은경우 인벤토리로 지급됩니다.');
                                        } else {
                                            $sender->sendMessage(AuctionHouseLoader::$prefix . '시간 기준치를 준수해주세요.');
                                        }
                                    } else {
                                        $sender->sendMessage(AuctionHouseLoader::$prefix . '최소, 최대가를 준수해주세요.');
                                    }
                                } else {
                                    $sender->sendMessage(AuctionHouseLoader::$prefix . '등록이 금지된 품목입니다.');
                                }
                            } else {
                                $sender->sendMessage(AuctionHouseLoader::$prefix . '아이템이 부족합니다.');
                            }
                        } else {
                            $sender->sendMessage(AuctionHouseLoader::$prefix . '공기는 거래소에 등록할 수 없습니다.');
                        }
                    } else {
                        $sender->sendMessage(AuctionHouseLoader::$prefix . '숫자로 입력해주세요.');
                    }
                } else {
                    $sender->sendMessage(AuctionHouseLoader::$prefix . '/거래소등록 [가격] [개수] [시간] - 손에 들고있는 아이템을 거래소에 올립니다.');
                }
            }
        }
    }
}