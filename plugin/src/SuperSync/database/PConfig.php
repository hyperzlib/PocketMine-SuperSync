<?php

namespace SuperSync\database;

use pocketmine\utils\Config;

class PConfig{
	
	public function __construct($file){
		$this->conf = new Config($file."Config.yml", Config::PROPERTIES, array(
			"url"=>"http://mcleague.xicp.net",
			"api"=>"sapi.php",
			"password"=>"mctl2333",
		));
	}
	public function getall(){
		return $this->conf->getall();
	}
}