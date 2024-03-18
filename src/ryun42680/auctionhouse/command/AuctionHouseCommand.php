<?php

namespace ryun42680\auctionhouse\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use ryun42680\auctionhouse\AuctionHouseLoader;

use function array_shift;
use function trim;

final class AuctionHouseCommand extends Command{

	public function __construct(){
		parent::__construct("거래소", "거래소를 이용합니다.", null,["auctionhouse"]);
		$this->setPermission(DefaultPermissions::ROOT_USER);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : void{
		if(!$sender instanceof Player){
			$sender->sendMessage(AuctionHouseLoader::$prefix . "게임내에서만 사용가능합니다.");
			return;
		}
		$keyword = array_shift($args);
		if(trim((string) $keyword) !== ""){
			AuctionHouseLoader::getInstance()->sendAuctionHouse($sender, $keyword);
		}else{
			AuctionHouseLoader::getInstance()->sendAuctionHouse($sender);
		}
	}
}
