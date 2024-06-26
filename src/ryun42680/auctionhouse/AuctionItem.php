<?php

namespace ryun42680\auctionhouse;

use naeng\NaengMailBox\mail\Mail;
use pocketmine\item\Item;
use pocketmine\player\Player;
use RoMo\MoneyCore\wallet\WalletFactory;
use ryun42680\auctionhouse\event\BuyAuctionItemEvent;
use ryun42680\lib\itemparser\ItemParser;
use ryun42680\offlinenotice\OfflineNotice;

use function strtolower;
use function time;

final class AuctionItem implements \JsonSerializable{

	public function __construct(
		private string $id, private string $owner, private int $price, private Item $item, private int $closeTime
	){}

	public function isClosed() : bool{
		return time() > $this->closeTime;
	}

	public function getId() : string{
		return $this->id;
	}

	public function isOwner(Player $player) : bool{
		return strtolower($this->owner) === strtolower($player->getName());
	}

	public function getOwner() : string{
		return $this->owner;
	}

	public function getPrice() : int{
		return $this->price;
	}

	public function getItem() : Item{
		return $this->item;
	}

	public function getCloseTime() : int{
		return $this->closeTime;
	}

	private function canBuy() : bool{
		return AuctionHouseLoader::getInstance()->getAuctionItem($this->id) instanceof AuctionItem;
	}

	public function buy(Player $player) : bool{
		if(!$this->canBuy()){
			return false;
		}

		$event = new BuyAuctionItemEvent($this, $player);
		$event->call();
		if($event->isCancelled()){
			return false;
		}

		$inventory = $player->getInventory();
		if(!$inventory->canAddItem($this->item)){
			$player->sendMessage(AuctionHouseLoader::$prefix . "인벤토리를 확인해주세요.");
			return false;
		}

		AuctionHouseLoader::getInstance()->unregisterItem($this->id);
		$mail = new Mail(
			title: "거래소 아이템 구매",
			senderName: "거래소",
			body: "거래소 아이템이 도착하였습니다.",
			items: [$this->item],
			expireTimeStamp: time() + 60 * 60 * 24 * 10
		);
		$mail->send($player);
		WalletFactory::getInstance()->getWallet($this->owner)->addCoin($this->price);
		OfflineNotice::getInstance()->getOfflinePlayer($this->owner)?->addNotice(
			AuctionHouseLoader::$prefix . "누군가 거래소에서 아이템을 구매했습니다! 잔고를 확인해보세요."
		);
		$player->sendMessage(AuctionHouseLoader::$prefix . "거래소에서 아이템을 구매했습니다.");
		return true;
	}

	public function jsonSerialize() : array{
		return [
			$this->id,
			$this->owner,
			$this->price,
			ItemParser::toString($this->item),
			$this->closeTime
		];
	}

	public static function jsonDeserialize(array $data) : self{
		return new self($data[0], $data[1], $data[2], ItemParser::fromString($data[3]), $data[4]);
	}
}
