<?php
namespace SuperSync;

use pocketmine\utils\Utils;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerChatEvent;
//use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

use onebone\economyapi\EconomyAPI;

use SuperSync\database\PConfig;
use SuperSync\database\Curl;


class Main extends PluginBase implements Listener{
	
	private $login,$newplayer,$timertimeout,$Ptimer,$move,$kick,$mode,$url;
	private $pper = array(),$sendmsg = array(),$username=array(),$ttt=array();
	private $service;
	
	public function onLoad(){
		$this->path = $this->getDataFolder();
		@mkdir($this->path);
		@mkdir($this->path."/Players");
		$this->newplayer=$this->path."/Players/";
	}
	
	public function onEnable(){ 
	    //$this->db = new Message($this->path);
		$this->conf = new PConfig($this->path);
		$conf = $this->conf->getall();
		$curl = new Curl($conf['password']);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info(TextFormat::AQUA."插件读取成功！");
		$this->getLogger()->info(TextFormat::AQUA."正在检查更新……");
		$webdata = json_decode($curl->get('http://mcleague.xicp.net/site/pl/suppersync.php',array('mode'=>'version','version'=>'2.6.5')), true);
		if($webdata['update']=='false'){
			$this->getLogger()->info($webdata['info']);
		} else {
			$this->getLogger()->info(TextFormat::YELLOW.'插件检查更新完成！已是最新版本。');
		}
		$this->getLogger()->info(TextFormat::AQUA."API地址： ".$conf['url'].'/'.$conf['api']);
		$this->url = $conf['url'];
		$webdata = $curl->get($conf['url'].'/'.$conf['api'],array('version'=>'2.6.5'));
		$webdata = json_decode($webdata, true);
		$this->service = $webdata['service'];
		if($webdata['name']=='weblogin' and $webdata['version']=='2.6.5'){
			$this->getLogger()->info(TextFormat::AQUA."API连接成功!");
			$this->mode = true;
		}	elseif($webdata['version']!='2.6.5') {
			$this->getLogger()->info(TextFormat::RED.'API与插件版本不符，可能会出现问题');
			$this->mode = true;
		} else {
			$this->getLogger()->info(TextFormat::AQUA."API连接失败，请检查网络");
			$this->mode = false;
		}
	}
	public function onJoin(PlayerJoinEvent $event){
		$this->conf = new PConfig($this->path);
		$conf = $this->conf->getall();
	    $player = $event->getPlayer();
		$user = strtolower($player->getName());
		$id = $player->getName();
		$url = $conf['url'];
		$cid = base64_encode($player->getClientId());
		$curl = new Curl($conf['password']);
		$pp = new Config($this->newplayer."$user.yml", Config::YAML);
		$scid = $pp->get("cid");
		$sip = $pp->get("ip");
		$time = $pp->get('time');
		$ip=$player->getAddress();
		$this->pper[$user]='off';
		$this->move[$user]=0;
		//date_default_timezone_set('Asia/Shanghai'); //系统时间差8小时问题
		if(!file_exists($this->newplayer."{$user}.yml")){
			$p = new Config($this->newplayer."{$user}.yml", Config::YAML, array(
				"username"=>$user,
				"cid"=>$cid,
				"ip"=>$ip,
			));
			$p->save();
			unset($p);
		}
		if($scid == $cid and $time + 172800 >= time()){ //如果cid匹配直接过
			$this->pper[$user]='on';
		} /*elseif(preg_match('/^192./',$ip)!=false){ //局域网也通过
			$this->pper[$user]='on';
		}*/ else { //验证是不是在网站登录了
			$webdata = $curl->get($url.'/'.$conf['api'].'?mode=login',array('username'=>$id,'ip'=>$ip,'cid'=>$cid));
			if (preg_match('/true/',$webdata)) {
				$this->pper[$user]='on';
				//保存cid
				$pp->set('cid', $cid);
				$pp->set('time', time());
				$pp->save();
			} elseif($webdata == 'false') {
				$this->pper[$user]='off';
				//交给游戏内登录
			} else {
				$this->pper[$user]='noreg';
			}
		}
		if($this->pper[$user] != 'on'){
			$player->sendTip('请打开聊天窗口登录');
		}
		if($this->pper[$user] == 'on' and !isset($this->sendmsg[$user])){
			$ip = $player->getAddress();
			$ipdata = $curl->get('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip='.$ip);
			$data = array('name' => $id);
			$ipdata = json_decode($ipdata, true);
			//$event->getPlayer()->setNameTag(TextFormat::GREEN.$data['name'].TextFormat::AQUA.'['.$id.']'."\n");
			if(empty($ipdata['ret'])){
				$ipdata['ret']=false;
			} elseif(preg_match('/192.168/',$ip)){
				$ipmsg='本地网络';
			} else {
				$ipmsg = $ipdata['country'].'  '.$ipdata['province'].'省 '.$ipdata['city'].'市';
			}
			if($this->service['money'] == true){
				$userdata = $curl->get($conf['url'].'/'.$conf['api'].'?mode=data',array('username'=>$id));
				$userdata = json_decode($userdata,true);
				EconomyAPI::getInstance()->setMoney($user,$userdata['credits']);
			}
			foreach ($this->getServer()->getOnlinePlayers() as $play){
				$play->sendMessage(TextFormat::GOLD."[提示]".TextFormat::GREEN.'来自 '.$ipmsg.TextFormat::AQUA.' 的 ['.TextFormat::YELLOW.$data['name'].TextFormat::AQUA."] 加入了游戏");
			}
			$this->getServer()->getLogger()->info(TextFormat::GOLD."[提示]".TextFormat::GREEN.'来自 '.$ipmsg.TextFormat::AQUA.' 的 ['.TextFormat::YELLOW.$data['name'].TextFormat::AQUA."] 加入了游戏");
			$event->getPlayer()->sendTip(TextFormat::GOLD.$data['name']."，欢迎回到服务器");
			//EconomyAPI::getInstance()->setMoney($user, intval($data['money']));
			$this->sendmsg[$user]='true';
		} elseif($this->pper[$user]=='noreg'){
			$event->getPlayer()->sendMessage("§b您还没有注册，请到\n§e§o".$this->url."\n§a§r注册。\n§e或在此输入电子邮箱开始注册。");
			$this->pper[$user]='noreg';
			$this->ttt[$user]['mode']=0;
		} else {
			$event->getPlayer()->sendMessage("§b您还没有登录，请到\n§e§o".$this->url."\n§a§r登录§e或在此输入密码。");
		}
	}
	
	public function onChat(PlayerChatEvent $event) {
		$player = $event->getPlayer();
		$user = strtolower($player->getName());
		if($this->pper[$user]=='off'){
			$event->setCancelled();
			$passwd = $event->getMessage();
			$this->conf = new PConfig($this->path);
			$conf=$this->conf->getall();
			$curl = new Curl($conf['password']);
			$id = $player->getName();
			$cid=base64_encode($player->getClientId());
			$ip=$player->getAddress();
			$pp = new Config($this->newplayer."$user.yml", Config::YAML);
			$webdata = $curl->get($conf['url'].'/'.$conf['api'].'?mode=submit',array('username'=>$id,'password'=>$passwd,'cid'=>$cid));
			if(preg_match('/true/',$webdata)){
				$event->getPlayer()->sendMessage("§e§o登录成功！欢迎进入游戏。");
				$pp->set('cid', $cid);
				$pp->set('time', time());
				$pp->save();
				$this->pper[$user]='on';
				if($this->service['money'] == true){
					$userdata = $curl->get($conf['url'].'/'.$conf['api'].'?mode=data',array('username'=>$id));
					$userdata = json_decode($userdata,true);
					EconomyAPI::getInstance()->setMoney($user,$userdata['credits']);
				}
			} else {
				$event->getPlayer()->sendMessage("§e§o登录失败，请检查密码。");
			}
		} elseif($this->pper[$user]=='noreg'){
			$event->setCancelled();
			$msg = $event->getMessage();
			$this->conf = new PConfig($this->path);
			$conf=$this->conf->getall();
			$curl = new Curl($conf['password']);
			$id = $player->getName();
			$cid=base64_encode($player->getClientId());
			$ip=$player->getAddress();
			$pp = new Config($this->newplayer."$user.yml", Config::YAML);
			$ttt = $this->ttt[$user];
			switch($ttt['mode']){
				case 0:
					if(preg_match('/@/',$msg)){
						$this->ttt[$user]['mail'] = $msg;
						$this->ttt[$user]['mode'] = 1;
						$event->getPlayer()->sendMessage("§e邮箱验证成功！开始注册吧。");
						$event->getPlayer()->sendMessage("§a请输入你的密码");
					} else {
						$event->getPlayer()->sendMessage(TextFormat::RED."请输入正确的电子邮箱账号。");
					}
					break;
				case 1:
					$this->ttt[$user]['password'] = $msg;
					$event->getPlayer()->sendMessage("§a你设定的密码为：§b".$msg);
					if($msg!='return'){
						$event->getPlayer()->sendMessage("§e请再次输入密码，或输入return返回重输密码");
					} else {
						$event->getPlayer()->sendMessage("§e请再次输入密码，或输入back返回重输密码");
					}
					$this->ttt[$user]['mode'] = 2;
					break;
				case 2:
					if($msg==$this->ttt[$user]['password']){
						$webdata = $curl->get($conf['url'].'/'.$conf['api'].'?mode=register',array('username'=>$id,'password'=>$this->ttt[$user]['password'],'ip'=>$ip,'mail'=>$this->ttt[$user]['mail']));
						if ($webdata=='true') {
							$event->getPlayer()->sendMessage("§e注册成功！请重新进入服务器并输入密码登录。");
							$this->ttt[$user]['mode'] = 3;
						} else {
							$event->getPlayer()->sendMessage("§e注册失败！与服务器通信时出错");
							$this->ttt[$user]['mode'] = 0;
						}
					} elseif($msg=='return' or $msg=='back') {
						$this->ttt[$user]['mode'] = 1;
						$event->getPlayer()->sendMessage("§e请重新设定密码。");
					} else {
						$event->getPlayer()->sendMessage("§e两次密码不符，请注意密码大小写。");
					}
					break;
				case 3:
					$event->getPlayer()->sendMessage("§e请重新进入服务器并输入密码登录。");
					break;
			}
		}
	}
	
	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
		//留空
	}
	
	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->conf = new PConfig($this->path);
		$conf = $this->conf->getall();
	    $player = $event->getPlayer();
		$curl = new Curl($conf['password']);
		$user = strtolower($player->getName());
		$id = $player->getName();
		if($this->pper[$user]=='on'){
			if($this->service['money'] == true){
				$money=intval(EconomyAPI::getInstance()->myMoney($user));
				$webdata = $curl->get($conf['url'].'/'.$conf['api'].'?mode=update',array('username'=>$id,'money'=>$money));
				if ($webdata=='true'){
					$this->getLogger()->info(TextFormat::AQUA.$id."的金币数据向API提交成功！");
				}
			}
		}
		unset($this->pper[$user]);
		unset($this->timertimeout[$user]);
		unset($this->Ptimer[$user]);
		unset($this->sendmsg[$user]);
	}
	public function onPlayerInteract(PlayerInteractEvent $event){
	    $this->permission($event);
	}		
	public function onBlockBreak(BlockBreakEvent $event){
		$this->permission($event);
	}	
	public function onEntityDamage(EntityDamageEvent $event){
		if($event->getEntity() instanceof Player){
			$user  = strtolower($event->getEntity()->getName());
			if(isset($this->pper[$user]) === false){
				$this->pper[$user] = "off";
				}
		    if($this->pper[$user] != "on" ){
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage("§b您还没有登录，请到\n§e§o".$this->url."\n§a§r登录后再进入服务器。");
			}
		}
	}
	public function onBlockPlace(BlockPlaceEvent $event){
		$this->permission($event);
	}
	public function onPlayerDrop(PlayerDropItemEvent $event){
		$this->permission($event);
	}
	public function onInventoryOpen(InventoryOpenEvent $event){
		$this->permission($event);
	}
	public function onPlayerItemConsume(PlayerItemConsumeEvent $event){
		$this->permission($event);
	}
	public function onPlayerMove(PlayerMoveEvent $event){
	    $user = strtolower($event->getPlayer()->getName());
		if(isset($this->pper[$user]) === false){
			$this->pper[$user]="off";
		}
		if($this->pper[$user] != "on" ){
			$this->move[$user]++;
			if($this->move[$user] >= 2){
				$event->setCancelled(true);
				$event->getPlayer()->onGround = true;
			}
		}
		unset($user);
	}
	public function onPickupItem(InventoryPickupItemEvent $event){
		$player = $event->getInventory()->getHolder();
		$user = strtolower($player->getName());
		if(!isset($this->pper[$user])){$this->pper[$user]=="off";}
		if($this->pper[$user] != "on" ){
			$event->setCancelled(true);
			}
	}
	public function permission($event){
	    $user = strtolower($event->getPlayer()->getName());		
		if(isset($this->pper[$user]) === false){
			$this->pper[$user]="off";
		}
		if($this->pper[$user] != "on" ){
			$event->setCancelled(true);
		}
		unset($user);
	}
	public function onPlayerRespwan(PlayerRespawnEvent $event){
		$player = $event->getPlayer();
		$world = $player->getLevel()->getName();
		$user = $player->getName();
		$y = (int)$player->getY();
		if($y <= 1){
			$x = $player->getX();
		    $z = $player->getZ();
			$spawn = $this->getServer()->getLevelByName($world)->getSpawn();			
			$event->setRespawnPosition($spawn);
			$this->getServer()->getLogger()->info(TextFormat::YELLOW."$user ".TextFormat::BLUE."卡虚空修复完成");	
		    unset($x,$z,$spawn);
		}
		unset($player,$world,$user,$y);
	}
}