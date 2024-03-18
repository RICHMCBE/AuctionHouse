<?php

namespace ryun42680\auctionhouse\event;

use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\event\Event;
use pocketmine\player\Player;
use ryun42680\auctionhouse\AuctionItem;

final class BuyAuctionItemEvent extends Event implements Cancellable{

	use CancellableTrait;

	public function __construct(private AuctionItem $auctionItem, private Player $player){}

	public function getAuctionItem() : AuctionItem{
		return $this->auctionItem;
	}

	public function getPlayer() : Player{
		return $this->player;
	}
}
