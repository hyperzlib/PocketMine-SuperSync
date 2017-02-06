<?php
$apipasswd = 'mctl2333'; //在此输入你的API密码，默认 mctl2333。 Enter your API password here. Default 'mctl2333'.

$desql = array('UNION','ORDER',';','AND','OR'); //SQL注入关键词
//error_reporting(E_ALL^E_NOTICE^E_WARNING); //关闭错误提示

//验证密码
if(empty($_COOKIE['sapi_password']) or $_COOKIE['sapi_password']!=$apipasswd){
	echo $_COOKIE['sapi_password'];
	exit;
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

file_put_contents('site/sapi.log', json_encode(array('mode'=>$_GET['mode'],'POST'=>$_POST,'COOOKIE'=>$_COOKIE))."\r\n", FILE_APPEND); //记录log
if(empty($_GET['mode'])){//显示版本
	$json=array(
		'name'=>'weblogin',
		'version'=>'2.6.5'
		'service'=>array('login'=>true,'register'=>true,'money'=>false);
		);
	echo json_encode($json);
	exit;
} elseif($_GET['mode']=='login') {//登录验证
	preg_match('/^[a-zA-Z\-_ ]{1,15}$/',$_POST['username'],$match);
	$username = $match[0];
	preg_match('/^((([1-9])|((0[1-9])|([1-9][0-9]))|((00[1-9])|(0[1-9][0-9])|((1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))))\.)((([0-9]{1,2})|(([0-1][0-9]{2})|(2[0-4][0-9])|(25[0-5])))\.){2}(([1-9])|((0[1-9])|([1-9][0-9]))|(00[1-9])|(0[1-9][0-9])|((1[0-9]{2})|(2[0-4][0-9])|(25[0-5])))$/',$_POST['ip'],$match);
	$ip = $match[0];
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$sql = str_replace($desql,'',$sql).';';
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
	$sql = str_replace($desql,'',$sql).';';
	$sip = $mysql->query($sql);
	$sip = $sip -> fetch();
	$sip = $sip['lastip'];
	if (!empty($sip)){
		if($sip==$ip){
			echo "true";
			$sql = str_replace($desql,'',$sql).';';
			$mysql->exec($sql);
		} else {
			echo "false";
		}
	} else {
		echo "false";
	}
} elseif($_GET['mode']=='submit'){//验证密码
	//取值
	preg_match('/^[a-zA-Z\-_ ]{1,15}$/',$_POST['username'],$match);
	$username = $match[0];
	$passwd = $_POST['password'];
	//数据库部分
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT * FROM `'.$uc['tab'].'members` WHERE username=\''.$username.'\'';
	$sql = str_replace($desql,'',$sql).';';
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
	preg_match('/^[a-zA-Z\-_ ]{1,15}$/',$_POST['username'],$match);
	$username = $match[0];
	$password = $_POST['password'];
	$mail = $_POST['mail'];
	$ip = $_POST['ip'];
	if(!preg_match('/^((([1-9])|((0[1-9])|([1-9][0-9]))|((00[1-9])|(0[1-9][0-9])|((1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))))\.)((([0-9]{1,2})|(([0-1][0-9]{2})|(2[0-4][0-9])|(25[0-5])))\.){2}(([1-9])|((0[1-9])|([1-9][0-9]))|(00[1-9])|(0[1-9][0-9])|((1[0-9]{2})|(2[0-4][0-9])|(25[0-5])))$/',$_POST['ip'])){
		echo 'true';
		exit;
	}
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
	$sql = str_replace($desql,'',$sql).';';
	$result = $mysql->exec($sql);
	if($result==true){
		echo 'true';
	} else {
		echo 'false';
	}
} elseif($_GET['mode']=='data'){
	preg_match('/^[a-zA-Z\-_ ]{1,15}$/',$_POST['username'],$match);
	$username = $match[0];
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$sql = str_replace($desql,'',$sql).';';
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
	$sql = str_replace($desql,'',$sql).';';
	$count = $mysql->query($sql);
	$count = $count -> fetch();
	echo json_encode($count);
} elseif($_GET['mode']=='update'){
	if(!isset($_POST['money'])){
		echo 'data error';
		exit;
	}
	preg_match('/^[a-zA-Z\-_ ]{1,15}$/',$_POST['username'],$match);
	$username = $match[0];
	$tab = $_config['db']['1']['tablepre'];
	$dsn = 'mysql:dbname='.$uc['name'].';host='.$uc['host'];
	$mysql = new PDO($dsn, $uc['user'], $uc['pw']);
	$sql = 'SELECT `uid` FROM `'.$uc['tab'].'members` where `username`=\''.$username.'\'';
	$sql = str_replace($desql,'',$sql).';';
	$result = $mysql->query($sql);
	$uid = $result->fetch();
	if(empty($uid)){
		echo 'noreg';
		exit;
	}
	$uid = $uid[0];
	$dsn = 'mysql:dbname='.$_config['db']['1']['dbname'].';host='.$_config['db']['1']['dbhost'];
	$mysql = new PDO($dsn, $_config['db']['1']['dbuser'], $_config['db']['1']['dbpw']);
	$sql = 'UPDATE `'.$tab.'common_member` SET `credits`=\''.$_POST['money'].'\' WHERE uid=\''.$uid.'\'';
	$sql = str_replace($desql,'',$sql).';';
	$count = $mysql->exec($sql);
	if($count){
		echo 'true';
	} else {
		echo 'false';
	}
}
?>