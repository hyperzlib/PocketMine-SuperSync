<?php

namespace SuperSync\database;

use pocketmine\utils\Utils;
use SuperSync\database\PConfig;

class Curl{
	public $password;
	
	public function __construct($password = ''){
		$this->password = $password;
	}
	
	public function get($url,$post = array(),$cookie=array('sapi_password'=>'')){
		$cookie['sapi_password'] = $this->password;
		$post = http_build_query($post);
		$cookie = http_build_query($cookie); 
		$opts = array(  
			'http'=>array(  
			'method'=>"POST",  
			'header'=>"Content-type: application/x-www-form-urlencoded\r\n".
			"User-Agent: PHP/".PHP_VERSION." (PocketMine; compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET4.0C; .NET4.0E)\r\n".
            "Content-length:".strlen($post)."\r\n" .   
            "Cookie: ".$cookie."\r\n" .   
            "\r\n",  
			'content' => $post,  
			)  
		);  
		$cxContext = stream_context_create($opts);  
		$webdata = @file_get_contents($url, false, $cxContext);
		return !empty($webdata)?$webdata:'';
	}
}