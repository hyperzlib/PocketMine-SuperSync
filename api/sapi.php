<?php
$apipasswd = 'mctl2333'; //在此输入你的API密码，默认 mctl2333。 Enter your API password here. Default 'mctl2333'.
$desql = array('UNION','ORDER',';','AND','OR','\'','"'); //SQL注入关键词
error_reporting(E_ALL^E_NOTICE^E_WARNING); //关闭错误提示
//验证密码
if(empty($_COOKIE['sapi_password']) or $_COOKIE['sapi_password']!=$apipasswd){
	echo $_COOKIE['sapi_password'];
	exit;
}
function desql($text){
	return str_replace(array('UNION','ORDER',';','AND','OR','\'','"'),'',$text);
}
include 'config/config_global.php';
include 'config/config_ucenter.php';
$uc = array(
	'tab' => str_replace('`'.UC_DBNAME.'`.','',UC_DBTABLEPRE),
	'name' => UC_DBNAME,
	'host' => UC_DBHOST,
	'user' => UC_DBUSER,
	'pw' => UC_DBPW,
);
//file_put_contents('site/sapi.log', json_encode(array('mode'=>$_GET['mode'],'POST'=>$_POST,'COOOKIE'=>$_COOKIE))."\r\n", FILE_APPEND); //记录log
if(empty($_GET['mode'])){//显示版本
	$json=array(
		'name'=>'weblogin',
		'version'=>'2.6.5',
		'service'=>array('login'=>true,'register'=>true,'money'=>false),
		);
	echo json_encode($json);
	exit;
} elseif($_GET['mode']=='login') {//登录验证
	$username = desql($_POST['username']);
	$ip = desql($_POST['ip']);
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$result = $mysql->query($sql);
	$uid = $result->fetch();
	if(empty($uid)){
		echo 'noreg';
		exit;
	}
	$uid = $uid[0];
	$dsn = 'mysql:dbname='.$_config['db']['1']['dbname'].';host='.$_config['db']['1']['dbhost'];
	$mysql = new PDO($dsn, $_config['db']['1']['dbuser'], $_config['db']['1']['dbpw']);
	$sql = 'SELECT `lastip` FROM `'.$tab.'common_member_status` WHERE uid=\''.$uid.'\'';
	$sip = $mysql->query($sql);
	$sip = $sip -> fetch();
	$sip = $sip['lastip'];
	if (!empty($sip)){
		if($sip==$ip){
			echo "true";
			$mysql->exec($sql);
		} else {
			echo "false";
		}
	} else {
		echo "false";
	}
} elseif($_GET['mode']=='submit'){//验证密码
	//取值
	$username = desql($_POST['username']);
	$passwd = desql($_POST['password']);
	//数据库部分
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT * FROM `'.$uc['tab'].'members` WHERE username=\''.$username.'\'';
	$result = $mysql->query($sql);
	$sqldata = $result->fetch();
	//判断密码
	$hash = md5(md5($passwd).$sqldata['salt']);
	if($hash == $sqldata['password']){
		echo 'true';
	} else {
		echo 'false';
	}
} elseif($_GET['mode']=='register') {
	$username = desql($_POST['username']);
	$password = desql($_POST['password']);
	$mail = desql($_POST['mail']);
	$ip = desql($_POST['ip']);
	//数据库部分
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT MAX(`uid`) FROM `'.$uc['tab'].'members`';
	$result = $mysql->query($sql);
	$uid = $result->fetch();
	$uid = $uid[0]+1;
	$hash = md5(time());
	$salt = substr($hash,rand(0,26),6);
	$password = md5(md5($password).$salt);
	$sql = 'INSERT INTO `uc_members` (`uid`, `username`, `password`, `email`, `myid`, `myidkey`, `regip`, `regdate`, `lastloginip`, `lastlogintime`, `salt`, `secques`) VALUES (\''.$uid.'\', \''.$username.'\', \''.$password.'\', \''.$mail.'\', \'\', \'\', \''.$ip.'\', \''.time().'\', \'0\', \'0\', \''.$salt.'\', \'\')';
	$result = $mysql->exec($sql);
	if($result==true){
		echo 'true';
	} else {
		echo 'false';
	}
} elseif($_GET['mode']=='data'){
	$username = desql($_POST['username']);
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$result = $mysql->query($sql);
	$uid = $result->fetch();
	if(empty($uid)){
		echo 'noreg';
		exit;
	}
	$uid = $uid[0];
	$dsn = 'mysql:dbname='.$_config['db']['1']['dbname'].';host='.$_config['db']['1']['dbhost'];
	$mysql = new PDO($dsn, $_config['db']['1']['dbuser'], $_config['db']['1']['dbpw']);
	$sql = 'SELECT * FROM `'.$tab.'common_member` WHERE uid=\''.$uid.'\'';
	$count = $mysql->query($sql);
	$count = $count -> fetch();
	echo json_encode($count);
} elseif($_GET['mode']=='update'){
	if(!isset($_POST['money'])){
		echo 'data error';
		exit;
	}
	$username = desql($_POST['username']);
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$result = $mysql->query($sql);
	$uid = $result->fetch();
	if(empty($uid)){
		echo 'noreg';
		exit;
	}
	$uid = $uid[0];
	$dsn = 'mysql:dbname='.$_config['db']['1']['dbname'].';host='.$_config['db']['1']['dbhost'];
	$mysql = new PDO($dsn, $_config['db']['1']['dbuser'], $_config['db']['1']['dbpw']);
	$sql = 'UPDATE `'.$tab.'common_member` SET `credits`=\''.intval($_POST['money']).'\' WHERE uid=\''.$uid.'\'';
	$count = $mysql->exec($sql);
	if($count){
		echo 'true';
	} else {
		echo 'false';
	}
}
?>