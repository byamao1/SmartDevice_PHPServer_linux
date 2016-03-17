<?php
//###增加负责管理账户注册、登录、退出
namespace Chat;

use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\Db;
use \GlobalData\Client;

require_once __DIR__ . '/../../GatewayWorker/Lib/Db.php';
require_once __DIR__ . '/../../GlobalData/src/Client.php';

class ClientManagement
{
	//上线信息
	protected static $global = null;
// 	protected static $onLineAccountList = array();
	
	public function __construct($ip = '127.0.0.1', $port = 2207)
	{
		self::$global = new Client("$ip:$port");
	}
	
	//返回值   0：成功   1：参数错误  2:已被注册
	public static function register($client_name, $client_pwd, $client_id)
	{
		if(empty($client_name)||empty($client_pwd)||empty($client_id))
		{
			return 1;
		}
		//检查是否已注册  注意：'$client_name'的单引号不能少，因为SQL语句需要单引号才能认为是字串，否则就是数值。
		$result = Db::instance('db1')->select('client_name')->from('chat_client_info')->where("client_name= '$client_name' ")->query();
		if(!isset($result)||empty($result))
		{
			//将用户基本信息存入已注册用户数据集。注意：由于这里使用了函数，会自动生成SQL语言，因此"$client_name"就不必像上面的那样写成"'$client_name'"
			$insert_id = Db::instance('db1')->insert('chat_client_info')->cols(array('client_name'=>"$client_name", 'client_pwd'=>"$client_pwd"))->query();
			return 0;
		}else{
			//已注册
			return 2;
		}
		//向List加入数据
// 		self::$onLineAccountList[$client_name] = $client_id;
		//向session加入数据
// 		$_SESSION['client_id'] = $client_id;
// 		$_SESSION['client_name'] = $client_name;
		
	
	}
	
	//返回值   0：成功    1：参数错误    2：未被注册    3：密码错误
	public static function login($client_name, $client_pwd, $client_id)
	{
		if(empty($client_name)||empty($client_pwd)||empty($client_id))
		{
			return 1;
		}
		//检查是否已注册，密码是否正确
		$result = Db::instance('db1')->select('client_name')->from('chat_client_info')->where("client_name= '$client_name' ")->query();
		if(!isset($result)||empty($result))
		{
			//未被注册
			return 2;
		}
		$result = Db::instance('db1')->select('client_name')->from('chat_client_info')->where("client_name= '$client_name' AND client_pwd= '$client_pwd' ")->query();
		if(!isset($result)||empty($result))
		{
			//密码错误
			return 3;
		}
		//上线
		$tmpclient_id = self::getCIDByName($client_name);
		if(($tmpclient_id <> 0)&&($tmpclient_id <> $client_id)){
			//把别处登录此用户的给踢了，通知被踢者
// 			$tmp_client_id = self::$onLineAccountList[$client_name];
			$tmp_client_id = self::$global->{$client_name};
			$new_message = array(
					'type'=>'say',
					'from_client_name' =>'ChatServer',
					'content'=>'You are kicked by other person!Please check your password!',
					'time'=>date('Y-m-d H:i:s'),
			);
			Gateway::sendToClient($tmp_client_id, json_encode($new_message));
			self::clearListById($tmp_client_id);
		}
		//向List加入数据
// 		self::$onLineAccountList[$client_name] = $client_id;
		self::$global->{$client_name} = $client_id;
// 		self::$onLineAccountList[$client_id] = $client_name;
		self::$global->{$client_id} = $client_name;
		//向session加入数据
// 		$_SESSION['client_id'] = $client_id;
// 		$_SESSION['client_name'] = $client_name;
		return 0;
	}
	//
	public static function logout($client_name, $client_id)
	{
		if(empty($client_name)||empty($client_id))
		{
			return false;
		}
		$tmpclient_id = self::getCIDByName($client_name);
		if(($tmpclient_id === 0)||($tmpclient_id <> $client_id))
		{
			return false;
		}else {
			//清除List对应的数据
			self::clearListByName($client_name);
			self::clearListById($client_id);
			//清除session对应的数据
// 			if(isset($_SESSION['client_name']))
// 			{
// 				unset($_SESSION['client_name']);
// 			}
// 			if(isset($_SESSION['client_id']))
// 			{
// 				unset($_SESSION['client_id']);
// 			}
			return true;
		}
		
	}
	
	//通过client_name查得关联的$client_id(看该用户是否在线)
	//返回值    0：未查到
	public static function getCIDByName($client_name)
	{
// 		if(isset(self::$onLineAccountList[$client_name]))
		if(isset(self::$global->{$client_name}))
		{
// 			return self::$onLineAccountList[$client_name];
			return self::$global->{$client_name};
		}else{
			return 0;
		}
	}
	
	//通过client_id查得关联的$client_name
	//返回值    null：未查到
	public static function getNameById($client_id)
	{
// 		if(isset(self::$onLineAccountList[$client_id]))
		if(isset(self::$global->{$client_id}))
		{
// 			return self::$onLineAccountList[$client_id];
			return self::$global->{$client_id};
		}else{
			return null;
		}
	}
	
	//清空指定client_name的上线信息
	public static function clearListByName($client_name)
	{
// 		if(isset(self::$onLineAccountList[$client_name]))
		if(isset(self::$global->{$client_name}))
		{
// 			unset(self::$onLineAccountList[$client_name]);
			unset(self::$global->{$client_name});
		}
	}
	
	//清空指定client_id的上线信息
	public static function clearListById($client_id)
	{
// 		if(isset(self::$onLineAccountList[$client_id]))
		if(isset(self::$global->{$client_id}))
		{
// 			unset(self::$onLineAccountList[$client_id]);
			unset(self::$global->{$client_id});
		}
	}
}
//#end