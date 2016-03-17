<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose 
 */
use \GatewayWorker\Lib\Gateway;
use \Chat\ClientManagement;

//### 它得放最前面，加入它才可以引用类ClientManagement
include_once __DIR__.'/ClientManagement.php';
//#end

class Event
{
	//### 增加  向发送方发送信息
	private static function sendMsgToSender($type, $content)
	{
		$new_message = array(
				'type'=>$type,
				'from_client_name' =>'ChatServer',
				'content'=>$content,
				'time'=>date('Y-m-d H:i:s'),
		);
		return Gateway::sendToCurrentClient(json_encode($new_message));
	}
	//#end
	
   /**
    * 有消息时
    * @param int $client_id
    * @param mixed $message
    */
   public static function onMessage($client_id, $message)
   {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        //### ClientManagement对象
        global $clientManagement;
        //#end
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if(!$message_data)
        {
            return ;
        }
        
        // 根据类型执行不同的业务
        switch($message_data['type'])
        {
        	
            // 客户端回应服务端的心跳
            case 'pong':
                return;
                
            //###增加注册
            case 'register':
            	// 判断是否有房间号
            	if(!isset($message_data['room_id']))
            	{
            		return self::sendMsgToSender('register_response', 'Error#\'room_id\' have not been set.');
//             		throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
            	}
            	if(!isset($message_data['client_name'])||!isset($message_data['client_pwd']))
            	{
            		return self::sendMsgToSender('register_response', 'Error#\'client_name\'  or \'client_pwd\' has not set.');
            	}
            	$client_name = htmlspecialchars($message_data['client_name']);
            	$client_pwd = htmlspecialchars($message_data['client_pwd']);
            	//注册
            	switch($clientManagement::register($client_name, $client_pwd, $client_id))
            	{
            		case 0:
            			echo "====== Client:$client_name is registered success. =====\n";
            			self::sendMsgToSender('register_response', "Success#Client:$client_name is registered success.");
            			break;
            		case 1:
            			return self::sendMsgToSender('register_response', 'Error#Name or password is invalid.');
            		case 2:
            			return self::sendMsgToSender('register_response', "Error#Client:$client_name has been registered by other person.");
            	}
//             	$_SESSION['client_id'] = $client_id;
            	// 把房间号昵称放到session中
//             	$room_id = $message_data['room_id'];
//             	$client_name = htmlspecialchars($message_data['client_name']);
//             	$_SESSION['room_id'] = $room_id;
//             	$_SESSION['client_name'] = $client_name;
            	return;
            
            // 客户端下线 message格式: {"type":"logout","client_name":"xx","room_id":"1"}
            case 'logout':
            	// 判断是否有房间号
            	if(!isset($message_data['room_id']))
            	{
            		return self::sendMsgToSender('logout_response', 'Error#\'room_id\' have not been set.');
            	}
//             	if(!isset($message_data['client_name']))
//             	{
//             		return self::sendMsgToSender('logout_response', 'Error#\'client_name\' have not been set.');
//             	}
//             	if(!isset($_SESSION['client_id']))
//             	{
//             		return self::sendMsgToSender('logout_response', 'Error#You are not login.');
//             	}
				$client_name = htmlspecialchars($message_data['client_name']);
				$session_client_id = $client_id;
				$online_client_id = $clientManagement::getCIDByName($client_name);
				if(($online_client_id === 0)||($online_client_id <> $session_client_id))
				{
					return self::sendMsgToSender('say_response', 'Error#You are not login.');
				}
//             	$client_id = $_SESSION['client_id'];
//             	$tmpclient_name = $_SESSION['client_name'];
            	if($clientManagement::logout($client_name, $client_id))
            	{
            		echo "===== Client:$tmpclient_name has been logout success. ======";
            		return self::sendMsgToSender('logout_response', "Success#Client:$tmpclient_name has been logout success.");
            	}else{
            		return self::sendMsgToSender('logout_response', "Error#Client:$tmpclient_name is fail to logout.");
            	}
            	return;
            //#end
            
            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':
                // 判断是否有房间号
                if(!isset($message_data['room_id']))
                {
                	return self::sendMsgToSender('login_response', 'Error#\'room_id\' have not been set.');
//                     throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
                }
                if(!isset($message_data['client_name'])||!isset($message_data['client_pwd']))
                {
                	return self::sendMsgToSender('login_response', 'Error#\'client_name\'  or \'client_pwd\' have not been set.');
                }
                $client_name = htmlspecialchars($message_data['client_name']);
                $client_pwd = htmlspecialchars($message_data['client_pwd']);
                //###增加                
                $session_client_id = $client_id;
                //避免在线时，未下线想切换账号
                if($clientManagement::getNameById($session_client_id) <> null)
                {
                	$session_client_name = $clientManagement::getNameById($session_client_id);
                	return self::sendMsgToSender('login_response', "Error#Please logout client:$session_client_name.");
                }
//                 	if($online_client_id === $session_client_id)
//                 	{
// 	                	$tmp_client_name = $_SESSION['client_name'];
//                 		$online_client_id = $clientManagement::getCIDByName($client_name);
// 	                	return self::sendMsgToSender('login_response', "Error#Please logout client:$client_name.");
//                 	}
//                 }
//                 $tmp_client_id = $_SESSION['client_id'];
//                 if(isset($tmp_client_id)&&($tmp_client_id <> $client_id))
//                 {
//                 	return self::sendMsgToSender('login_response', 'Error#You are not login.');
//                 }
                //登录
                switch($clientManagement::login($client_name, $client_pwd, $client_id))
                {
                	case 0: 
                		echo "====== Client:$client_name is login. =====\n";
                		self::sendMsgToSender('login_response', "Success#Client:$client_name is login success.");
                		break;
                	case 1:
                		return self::sendMsgToSender('login_response', 'Error#Name or password is error.');
                	case 2:
                		return self::sendMsgToSender('login_response', "Error#Client:$client_name has not been registered.");
                	case 3:
                		return self::sendMsgToSender('login_response', "Error#Client:$client_name's password is error!");
                }
//                 $_SESSION['client_id'] = $client_id;
               
                //#end
                
                // 把房间号昵称放到session中
                $room_id = $message_data['room_id'];
//                 $client_name = htmlspecialchars($message_data['client_name']);
                $_SESSION['room_id'] = $room_id;
//                 $_SESSION['client_name'] = $client_name;
              
                
                
                // 获取房间内所有用户列表 
//                 $clients_list = Gateway::getClientInfoByGroup($room_id);
//                 foreach($clients_list as $tmp_client_id=>$item)
//                 {
//                     $clients_list[$tmp_client_id] = $item['client_name'];
//                 }
//                 $clients_list[$client_id] = $client_name;
                
                // 转播给当前房间的所有客户端，xx进入聊天室 message {type:login, client_id:xx, name:xx} 
                //###不进行转播
//                 $new_message = array('type'=>$message_data['type'], 'client_id'=>$client_id, 'client_name'=>htmlspecialchars($client_name), 'time'=>date('Y-m-d H:i:s'));
//                 Gateway::sendToGroup($room_id, json_encode($new_message));
                //#end
                Gateway::joinGroup($client_id, $room_id);
               
                // 给当前用户发送用户列表 
//                 $new_message['client_list'] = $clients_list;
//                 Gateway::sendToCurrentClient(json_encode($new_message));
                return;
                
            // 客户端发言 message: {type:say, to_client_id:xx, content:xx}
            case 'say':
                // 非法请求
                if(!isset($_SESSION['room_id']))
                {
                	return self::sendMsgToSender('say_response', 'Error#\'room_id\' have not been set.');
//                     throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
                }
                //###增加
                //检查是否已登录
//                 if((!isset($_SESSION['client_name']))||(!isset($_SESSION['client_id'])))
//                 {
//                 	return self::sendMsgToSender('say_response', 'Error#You are not login.');
//                 }
				$client_name = htmlspecialchars($message_data['client_name']);
				$session_client_id = $client_id;
				$online_client_id = $clientManagement::getCIDByName($client_name);
				if(($online_client_id === 0)||($online_client_id <> $session_client_id))
				{
					return self::sendMsgToSender('say_response', "Error#You are not login Client:$client_name.");
				}

//                 $client_name = $_SESSION['client_name'];
//                 $client_id = $_SESSION['client_id'];
//                 $tmp_client_id = $clientManagement::getCIDByName($client_name);
//                 if(($tmp_client_id === 0)||($tmp_client_id <> $client_id))
//                 {
//                 	return self::sendMsgToSender('say_response', 'Error#You are not login.');
//                 }
                //#end
                $room_id = $_SESSION['room_id'];
//                 $client_name = $_SESSION['client_name'];
                
                // 私聊
                if($message_data['to_client_id'] != 'all')
                {
                    $new_message = array(
                        'type'=>'say',
                    	//###不用client_id
//                         'from_client_id'=>$client_id, 
                        'from_client_name' =>$client_name,
//                         'to_client_id'=>$message_data['to_client_id'],
//                         'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
						//###改为原文转发
                    	'content'=>$message_data['content'],
                        'time'=>date('Y-m-d H:i:s'),
                    );
//                     Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
					//###改为通过name查找client_id再发送消息
                    $to_client_name = htmlspecialchars($message_data['to_client_name']);
                    $tmp_client_id = $clientManagement::getCIDByName($to_client_name);
					if($tmp_client_id <> 0)
					{
                    	echo "========Find the Client: $to_client_name========\n";
                    	self::sendMsgToSender('say_response', "Success#Message has sent to Client: $to_client_name.");
                    	return Gateway::sendToClient($tmp_client_id, json_encode($new_message));
                    }else{
                    //未找到该用户
//                     $new_message['content'] = 'Error#Message cannot reach Client.';
                    	echo "========Cannot find the Client: $to_client_name========\n";
//                     return Gateway::sendToCurrentClient(json_encode($new_message));
                    	return self::sendMsgToSender('say_response', "Error#Message cannot reach the Client: $to_client_name.");
                    }
                    //#end
                    
                    //###不需要再告诉发送者
//                     $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
//                     return Gateway::sendToCurrentClient(json_encode($new_message));
                }
                //向房间内群发
                $new_message = array(
                    'type'=>'say', 
                    'from_client_id'=>$client_id,
                    'from_client_name' =>$client_name,
                    'to_client_id'=>'all',
                    'content'=>nl2br(htmlspecialchars($message_data['content'])),
                    'time'=>date('Y-m-d H:i:s'),
                );
                return Gateway::sendToGroup($room_id ,json_encode($new_message));
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */
   public static function onClose($client_id)
   {
       // debug
       echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
       
       // 从房间的客户端列表中删除
       if(isset($_SESSION['room_id']))
       {
       		//### 控制台显示用户连接中断，下线
//        		if(!isset($_SESSION['client_name']))
//        		{
//        			return;
//        		}
//        		$client_name = $_SESSION['client_name'];
//        		$client_id = $_SESSION['client_id'];
			global $clientManagement;
			$client_name = $clientManagement::getNameById($client_id);
			//如果没上线，就不必下线了
			if($client_name === 0)
				return;
       		if($clientManagement::logout($client_name, $client_id))
       		{
       			echo "==== Client:$client_name is logout.The connection is closed. ===\n";
       			return;// self::sendMsgToSender('say_response', "Success#Client:$client_name has been logout success.");
       		}else{
       			echo "==== Error#Client:$client_name is fail to logout. ===\n";
       			return;// self::sendMsgToSender('say_response',"Error#Client:$client_name is fail to logout.");
       		}
       		
       		//#end
           $room_id = $_SESSION['room_id'];
//            $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
//            Gateway::sendToGroup($room_id, json_encode($new_message));
       }
   }
  
  
}
