<?php

namespace ryun42680\auctionhouse\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use RoMo\CommandCore\command\parameter\IntParameter;
use RoMo\CommandCore\command\parameter\OneEnumParameter;
use RoMo\CommandCore\CommandCore;
use ryun42680\auctionhouse\AuctionHouseLoader;
use ryun42680\guideloader\GuideLoader;
use ryun42680\lib\itemparser\ItemParser;

use function array_shift;
use function is_numeric;

final class AuctionManagementCommand extends Command{

	public function __construct(){
		parent::__construct("거래소관리", "거래소를 관리합니다.");
		$this->setPermission(DefaultPermissions::ROOT_USER);

		CommandCore::getInstance()->registerCommandOverload($this,
			CommandCore::createOverload(
				new OneEnumParameter("물품제거")
			),
			CommandCore::createOverload(
				new OneEnumParameter("가격"),
				new IntParameter("최저가"),
				new IntParameter("최고가")
			),
			CommandCore::createOverload(
				new OneEnumParameter("금지품목")
			)
		);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$sender instanceof Player){
			$sender->sendMessage(AuctionHouseLoader::$prefix . "게임내에서만 사용가능합니다.");
			return;
		}

		if(
			!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)
			&& !GuideLoader::getInstance()->isGuide($sender)
		){
			$sender->sendMessage(AuctionHouseLoader::$prefix . "당신은 이 명령어를 사용할 권한이 없습니다.");
			return;
		}

		$subcommand = array_shift($args) ?? "";
		if($subcommand === "물품제거"){
			AuctionHouseLoader::getInstance()->sendAuctionHouse($sender, null, true);
		}elseif($subcommand === "가격"){
			$minPrice = array_shift($args);
			$maxPrice = array_shift($args);
			if(!is_numeric($minPrice) || !is_numeric($maxPrice)){
				$sender->sendMessage(AuctionHouseLoader::$prefix . "/거래소관리 가격 [최저가] [최대가] - 가격 기준을 설정합니다.");
				return;
			}

			$item = $sender->getInventory()->getItemInHand();
			if($item->isNull()){
				$sender->sendMessage(AuctionHouseLoader::$prefix . "공기의 가격은 설정할 수 없습니다.");
				return;
			}

			$minPrice = (int) $minPrice;
			$maxPrice = (int) $maxPrice;
			AuctionHouseLoader::$standard[ItemParser::toString($item->setCount(1))] = [
				$minPrice,
				$maxPrice
			];
			$sender->sendMessage(
				AuctionHouseLoader::$prefix
				. "{$item->getName()} §r§7의 기준가를 설정했습니다 ($minPrice / $maxPrice)"
			);
		}elseif($subcommand === "금지품목"){
			$item = $sender->getInventory()->getItemInHand();
			if($item->isNull()){
				$sender->sendMessage(AuctionHouseLoader::$prefix . "공기는 제한할 수 없습니다.");
				return;
			}

			AuctionHouseLoader::$ban[] = ItemParser::toString($item->setCount(1));
			$sender->sendMessage(AuctionHouseLoader::$prefix . $item->getName() . "§r§7을(를) 제한했습니다.");
		}else{
			$sender->sendMessage(AuctionHouseLoader::$prefix . "/거래소관리 물품제거 - 물품을 강제 제거합니다. (반환 X)");
			$sender->sendMessage(AuctionHouseLoader::$prefix . "/거래소관리 가격 [최저가] [최대가] - 가격 기준을 설정합니다.");
			$sender->sendMessage(AuctionHouseLoader::$prefix . "/거래소관리 금지품목 - 손에 든 아이템을 금지품목 설정합니다.");
		}
	}
}
