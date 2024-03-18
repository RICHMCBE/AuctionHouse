<?php

namespace ryun42680\auctionhouse;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use naeng\NaengMailBox\mail\Mail;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;
use RoMo\MoneyCore\moneyFormat\KoreanFormat;
use RoMo\MoneyCore\wallet\WalletFactory;
use RoMo\SaveCore\SaveCore;
use ryun42680\auctionhouse\command\AuctionHouseCommand;
use ryun42680\auctionhouse\command\AuctionManagementCommand;
use ryun42680\auctionhouse\command\RegisterItemCommand;
use ryun42680\lib\provider\DataProvider;
use ryun42680\lib\provider\ProviderHandler;

use function array_map;
use function array_merge;
use function array_values;
use function is_null;
use function strpos;
use function strtolower;
use function time;

final class AuctionHouseLoader extends PluginBase{

	public static string $prefix = "§l§6 • §r§7";

	private ?DataProvider $provider;
	private ?Config $config = null;

	public static array $standard;
	public static array $ban;

	use SingletonTrait {
		getInstance as _getInstance;
	}

	protected function onEnable() : void{
		if(!ProviderHandler::isRegistered($this)){
			ProviderHandler::register($this, AuctionItem::class);
			$this->provider = ProviderHandler::get($this);
		}
		$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, [[], []]);
		self::$standard = $this->config->getAll()[0];
		self::$ban = $this->config->getAll()[1];
		$this->getServer()->getCommandMap()->registerAll(strtolower($this->getName()), [
			new AuctionHouseCommand(),
			new AuctionManagementCommand(),
			new RegisterItemCommand()
		]);
		SaveCore::getInstance()->register(function() : void{
			if($this->config instanceof Config){
				$this->config->setAll([self::$standard, self::$ban]);
				$this->config->save();
			}
			if($this->provider instanceof DataProvider){
				$this->provider->save();
			}
		}, $this);
		if(!InvMenuHandler::isRegistered()){
			InvMenuHandler::register($this);
		}
	}

	protected function onDisable() : void{
		if($this->config instanceof Config){
			$this->config->setAll([self::$standard, self::$ban]);
			$this->config->save();
		}
		if($this->provider instanceof DataProvider){
			$this->provider->save();
		}
	}

	protected function onLoad() : void{
		self::setInstance($this);
	}

	public static function getInstance() : AuctionHouseLoader{
		return self::_getInstance();
	}

	public function registerItem(AuctionItem $auctionItem) : void{
		$this->provider->setObject($auctionItem->getId(), $auctionItem);
	}

	public function unregisterItem(string $id) : void{
		$this->provider->deleteObject($id);
	}

	public function returnItem(string $id) : void{
		$auctionItem = $this->getAuctionItem($id);
		$this->unregisterItem($id);
		$mail = new Mail(
			title: "거래소 아이템 반환",
			senderName: "거래소",
			body: "거래소 아이템이 반환되었습니다.",
			items: [$auctionItem->getItem()],
			expireTimeStamp: time() + 60 * 60 * 24 * 10
		);
		$mail->send($auctionItem->getOwner());
	}

	public function getAuctionItem(string $id) : ?AuctionItem{
		return $this->provider->getObject($id);
	}

	public function getItemsByPage(int $page) : array{
		if($page <= 0){
			return [];
		}

		$result = [];
		$this->returnItems();
		for($i = ($page * 36) - 36; $i < $page * 36; $i++){
			$auctionItem = array_values($this->provider->getAll())[$i] ?? null;
			if(!is_null($auctionItem)){
				$result[] = $auctionItem;
			}
		}
		return $result;
	}

	private function returnItems() : void{
		/**
		 * @var string      $id
		 * @var AuctionItem $auctionItem
		 */
		foreach($this->provider->getAll() as $id => $auctionItem){
			if($auctionItem->isClosed()){
				$this->returnItem($id);
			}
		}
	}

	public function getItemsByWord(string $word) : array{
		$result = [];
		/** @var AuctionItem $auctionItem */
		foreach($this->provider->getAll() as $auctionItem){
			if($auctionItem->isClosed()){
				$this->returnItem($auctionItem->getId());
			}elseif(strpos($auctionItem->getItem()->getName(), $word)){
				$result[] = $auctionItem;
			}
		}
		return $result;
	}

	public function sendAuctionHouse(Player $player, ?string $keyword = null, bool $deleteMode = false) : void{
		$invMenu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
		$invMenu->setName("거래소");
		$this->reloadMenu($invMenu->getInventory(), $player, 1, $keyword);
		$invMenu->setListener(function(InvMenuTransaction $transaction)
		use ($deleteMode, $invMenu) : InvMenuTransactionResult{
			$action = $transaction->getAction();
			$sourceItem = $action->getSourceItem();
			$namedtag = $sourceItem->getNamedTag();
			$player = $transaction->getPlayer();
			$page = $namedtag->getInt("page", 1);
			$inventory = $action->getInventory();
			(match ($action->getSlot()) {
				45      => function() use ($sourceItem, $inventory, $player, $page){
					if(!$sourceItem->isNull()){
						$this->reloadMenu($inventory, $player, $page - 1);
					}
				},
				53      => function() use ($sourceItem, $inventory, $player, $page){
					if(!$sourceItem->isNull()){
						$this->reloadMenu($inventory, $player, $page + 1);
					}
				},
				default => function() use ($namedtag, $deleteMode, $inventory, $player, $invMenu){
					$id = $namedtag->getString("auction", "");
					$auctionItem = AuctionHouseLoader::getInstance()->getAuctionItem($id);
					if(!$auctionItem instanceof AuctionItem){
						return;
					}

					if($deleteMode){
						$this->returnItem($id);
						$player->removeCurrentWindow();
						$player->sendMessage(AuctionHouseLoader::$prefix . "작업 완료.");
						return;
					}

					if($auctionItem->isOwner($player)){
						$player->sendMessage(AuctionHouseLoader::$prefix . "등록한 아이템을 돌려받았습니다.");
						$player->removeCurrentWindow();
						$this->returnItem($id);
						return;
					}

					$player->removeCurrentWindow();
					$confirmMenu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
					$confirmMenu->setName("거래소 구매 확정");
					$inventory = $confirmMenu->getInventory();
					$inventory->setItem(
						11,
						VanillaBlocks::STAINED_GLASS()
									 ->setColor(DyeColor::RED())
									 ->asItem()
									 ->setCustomName("§r§l§c구매 취소하기")
									 ->setLore([" ", "§r§7터치하여 거래소 메뉴로 이동"])
					);
					$inventory->setItem(13, $auctionItem->getItem());
					$wallet = WalletFactory::getInstance()->getWallet($player->getName());
					if($wallet->getCoin() >= $auctionItem->getPrice()){
						$inventory->setItem(15,
							VanillaBlocks::STAINED_GLASS()->setColor(DyeColor::GREEN())->asItem()
										 ->setCustomName("§r§l§a구매 확정하기")->setLore([
									" ", "§r§7터치하여 아이템 구매"
								]));
					}
					$confirmMenu->setListener(function(InvMenuTransaction $transaction)
					use ($invMenu, $auctionItem) : InvMenuTransactionResult{
						$player = $transaction->getPlayer();
						$slot = $transaction->getAction()->getSlot();
						if($slot === 11){
							$player->removeCurrentWindow();
							$this->reloadMenu($invMenu->getInventory(), $player);
							$invMenu->send($player);
						}elseif($slot === 15){
							$wallet = WalletFactory::getInstance()->getWallet($player->getName());
							if($wallet->getCoin() >= ($price = $auctionItem->getPrice())){
								if($auctionItem->buy($player)){
									$wallet->reduceCoin($price);
								}
								$player->removeCurrentWindow();
							}
						}
						return $transaction->discard();
					});
					$confirmMenu->send($player);
				}
			})();
			return $transaction->discard();
		});
		$invMenu->send($player);
	}

	private function reloadMenu(Inventory $inventory, Player $player, int $page = 1, ?string $keyword = null) : void{
		$inventory->clearAll();
		if(empty($keyword)){
			if(!empty($this->getItemsByPage($page + 1))){
				$right_arrow = VanillaItems::FIRE_CHARGE();
				$right_arrow->getNamedTag()->setInt("page", $page);
				$inventory->setItem(53, $right_arrow
					->setCustomName("§r§l§f다음 페이지 (" . $page . ")")
					->setLore([" ", "§r§7터치하여 다음 페이지로 이동"])
				);
			}
			if(!empty($this->getItemsByPage($page - 1))){
				$left_arrow = VanillaItems::SPIDER_EYE();
				$left_arrow->getNamedTag()->setInt("page", $page);
				$inventory->setItem(45, $left_arrow
					->setCustomName("§r§l§f이전 페이지 (" . $page . ")")
					->setLore([" ", "§r§7터치하여 이전 페이지로 이동"])
				);
			}
			$items = self::getInstance()->getItemsByPage($page);
		}else{
			$items = self::getInstance()->getItemsByWord($keyword);
		}
		$inventory->setItem(36, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(37, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(38, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(39, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(40, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(41, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(42, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(43, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(44, VanillaBlocks::BARRIER()->asItem()->setCustomName("§r"));
		$inventory->setItem(49, VanillaItems::BOOK()
											->setCustomName("§r§l§f거래소 사용법")
											->setLore([
												" ",
												"§r§7물건 등록 - /거래소등록 [가격] [개수] [시간]",
												"§r§7물건 찾기 - /거래소 [키워드]"
											]));
		$contents = array_map(static function(AuctionItem $auctionItem) use ($player) : Item{
			$item = clone $auctionItem->getItem();
			$item->getNamedTag()->setString("auction", $auctionItem->getId());
			return $item->setLore(array_merge($item->getLore(), $auctionItem->isOwner($player)
				? [
					" ",
					"§r§c터치하여 회수", " "
				]
				: [
					" ",
					"§r§b- - - - - - - - - - - - -",
					" ",
					"§r§f이름: " . $item->getName(),
					"§r§f주인: " . $auctionItem->getOwner(),
					"§r§f판매가: " . (new KoreanFormat ())->getStringFormat($auctionItem->getPrice()),
					"§r§f남은 시간: " . TimeParser::koreanTimeFormat($auctionItem->getCloseTime() - time()),
					" ",
					"§r§b- - - - - - - - - - - - -"
				]));
		}, $items);
		foreach($contents as $i => $item){
			$inventory->setItem($i, $item);
		}
	}
}
