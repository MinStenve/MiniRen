<?php
/* 微信公众平台模拟操作类
*/

class Mock{
	public $cookie = ''; public $token = ''; public $user = ''; public $pwd = ''; public $results = ''; public $gl = array() ;public $profile=array();
	public $ul = array() ;
	//引入Snoopy 类
	function __construct(){
        import("@.ORG.Snoopy");
    }

    //登陆  返回 cookie 和 token 数组
    public function login($user = '',$pwd = ''){
    	if($user == '') $user = $this->user;
    	if($pwd == '') $pwd = $this->pwd;
    	$snoopy = new Snoopy();
		$submit = "http://mp.weixin.qq.com/cgi-bin/login?lang=zh_CN";
		$snoopy->referer = "http://mp.weixin.qq.com/";
		$snoopy->rawheaders["Origin"] = "http://mp.weixin.qq.com";
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$snoopy->rawheaders["Pragma"] = "no-cache";
		$post["username"] = $user;
		$post["pwd"] = md5($pwd);
		$post['imgcode'] = '';
		$post["f"] = "json";
		$snoopy->submit($submit,$post);
		//取出token
		$rs = json_decode($snoopy->results,true);

		preg_match('/token=(\d+)/',$rs['redirect_url'],$token);
		//取得token[1]就是token值
		$cookie = '';
		foreach ($snoopy->headers as $key => $value) {
			$value = trim($value);
			if(strpos($value,'Set-Cookie: ') || strpos($value,'Set-Cookie: ') === 0){
				$tmp = str_replace("Set-Cookie: ","",$value);
				$tmp = str_replace("Path=/","",$tmp);
				$cookie .= $tmp.';';
			}
		}

		if(strlen($cookie) > 20){
			$a['cookie'] = $cookie;
			$a['token'] = $token[1];
			$this->cookie = $cookie;
			$this->token  = $token[1];
			$this->results = $a;
			return $a; //返回数组
		}else{
			return false;
		}
    }

    //从实时消息页面抓取fakeid 传入参数 消息内容
	public function fetchfakeid($msg,$count=20,$times=1){
		$snoopy = new Snoopy();
		$submit="http://mp.weixin.qq.com/cgi-bin/message?t=message/list&count=".$count."&day=0&token=".$this->token."&lang=zh_CN";
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/message?t=message/list&count=20&day=0&token=".$this->token."&lang=zh_CN";
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->fetch($submit);
		preg_match('|keyword\s+:\s+\"\",\s+list\s+\:\s+\((.*?)\).msg_item|Uis',$snoopy->results,$json);
		$arr = json_decode($json[1],true);
		if(!isset($arr['msg_item'])){
			if($times < 3){
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->fetchfakeid($msg,$count,$times+1);
			}else{
				$this->results = false;
				return false;
			}
		}else{
			$this->results = false;
			$much = 0; //几个匹配的 避免混淆
			//取出消息列表 如果匹配到就返回该对象
			for($i=0;$i<count($arr['msg_item']);$i++){
				if(trim($arr['msg_item'][$i]['content']) == trim($msg)){
					$much = $much+1; //+1
					$this->results = $arr['msg_item'][$i];
					//return $arr [id] => 29 [type] => 1 [fakeid] => 2633168204 [nick_name] => 00 [date_time] => 1377334471 [content] => Bd lh lh [source] => [msg_status] => 4 [has_reply] => 0 [refuse_reason] =>
				}
			}
			if($much == 1){
				return $this->results;
			}else{
				$this->results =false;
				return false;
			}
		}
	}

    //单用户消息发送
    public function sendmsg($fakeid,$msg,$times = 1){
		$snoopy = new Snoopy();
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/singlesendpage?t=message/send&action=index&tofakeid=".$fakeid."&token=".$this->token."&lang=zh_CN";
		$snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$snoopy->rawheaders["X-Requested-With"] = "XMLHttpRequest";
		$snoopy->rawheaders["Origin"] = "http://mp.weixin.qq.com";
		$post = array();
		$post['tofakeid'] = $fakeid;
		$post['type'] = 1;
		$post['content'] = $msg;
		$post['token'] = $this->token;
		$post['f'] = 'json';
		$post['ajax'] = 1;
		$post['lang'] = 'zh_CN';
		$post['imgcode'] = '';
		$post['t'] = 'ajax-response';
		$post['random'] = '0.'.rand(1000,9999).rand(1000,9999).rand(1000,9999).rand(1000,9999);
		$submit = "http://mp.weixin.qq.com/cgi-bin/singlesend";
		$snoopy->submit($submit,$post);
		$result = $snoopy->results;
		$result	=	json_decode($result,true);
		if($result['base_resp']['ret'] != 0){
			if($times == 0){
				$this->results = false;
				return false;
			}elseif($times < 3){
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->sendmsg($fakeid,$msg,$times+1);
			}else{
				$this->results = false;
				return false;
			}
		}else{
			$this->results = true;
			return true;
		}
	}


	//保存用户头像 type= 0 头像 >1 公众账号二维码 风格 1 ~ 17
	public function saveavatar($fakeid,$type = 0,$dir = "Public/uploads/mp/",$times = 1){
		//https://mp.weixin.qq.com/misc/getqrcode?fakeid=2391762001&token=1238808047&style=1
		//https://mp.weixin.qq.com/misc/getheadimg?fakeid=2391762001&token=1238808047&lang=zh_CN
		$snoopy = new Snoopy();
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize=20&pageidx=0&type=0&groupid=0&token=".$this->token."&lang=zh_CN";
		if($type == 0){
			$submit = "http://mp.weixin.qq.com/misc/getheadimg?fakeid=".$fakeid."&token=".$this->token.'&lang=zh_CN';
		}elseif($type > 0){
			$submit = "http://mp.weixin.qq.com/misc/getqrcode?fakeid=".$fakeid."&token=".$this->token."&style=".$type;
		}
		$snoopy->fetch($submit);
		if(!is_dir($dir)) mkdir($dir,0777,true);
		$filename = $dir.md5($fakeid.rand(1,1000)).'.jpg';
		if(!strpos($snoopy->results,'登录超时')){
			$handle = fopen($filename,"wb");
			fwrite($handle, $snoopy->results);//写入抓得内容
			fclose($handle);
			$this->results = $filename;
			return $filename;  //返回文件路径
		}else{
			if($times < 3){
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->saveavatar($fakeid,$type,$dir,$times+1);
			}else{
				$this->results = false;
				return false;
			}
		}

	}

	#计算用户总数#
	public function countusers(){
		set_time_limit(0);
		//采集到用户组信息
		$this->getGroupList() ;
		$groups = $this->gl ;
		if($groups == false){
			$this->results = false;return false; //返回
		}else{
			$users = 0;
			foreach($groups as $k){
				if($k['id'] != 1) $users = $users+$k['cnt'];
			}
			$this->results = $users;return $users; //返回
		}
	}

   //取得全部用户信息 并 发送内容
	public function sendAllUsers($mt,$content){
		set_time_limit(0);
		//采集到用户组信息
		$this->getGroupList() ;
		$groups = $this->gl ;
		if($groups == false){
			//file_put_contents('err1.txt','00');
			$this->results = false;//"分组信息获取失败!";
			return false; //返回
		}else{
			$success = 0; //成功发送的用户数
			$much	=	50; //每页多少条
			//根据用户组进行遍历采集 用户信息
			foreach($groups as $k){
				if($k['id'] != 1){
					if($k['cnt'] > 50){
						//分页采集
						$pages = $k['cnt']/$much; //除以 获取总页数
						if(intval($pages) != $pages)	$pages = intval($pages)+1; //如不能整除，则取整数 +1
						//循环操作
						//$arr = range($pages,0); //倒序生成数组
						for($p=0;$p<$pages;$p++){
							$this->fetchuser($k['id'],$p,$much); //遍历用户组
							$u = $this->ul ;
							if(!is_array($u)){
								//file_put_contents('err2.txt','');
								if($success == 0) $success = false;
								$this->results = $success;
								return $success; //返回已发送成功数量并停止
							}else{
								foreach($u as $s){
									//发送
									sleep(rand(1,3));//间隔操作
									if($mt == 1){
										//文本
										$r = $this->sendmsg($s['id'],$content);
										//判断发送结果
										if($r == true) $success = $success+1; //+1发送成功
									}elseif($mt == 2){
										//图文
										$r = $this->sendNews($s['id'],$content);
										//判断发送结果
										if($r == true) $success = $success+1; //+1发送成功
									}
								}
							}
						}
					}else{//不足一百用户 也就是 不足显示一页
						$this->fetchuser($k['id'],0,$much); //遍历用户组
						$u = $this->ul ;
						if(!is_array($u)){
							if($success == 0) $success = false;
							$this->results = $success;
							//file_put_contents('err3.txt','');
							return $success; //返回已发送成功数量并停止
						}else{
							foreach($u as $s){
								//发送
								sleep(rand(1,3));//间隔操作
								if($mt == 1){
									//文本
									$r = $this->sendmsg($s['id'],$content);
									//判断发送结果
									if($r == true) $success = $success+1; //+1发送成功
								}elseif($mt == 2){
									//图文
									$r = $this->sendNews($s['id'],$content);
									//判断发送结果
									if($r == true) $success = $success+1; //+1发送成功
								}
							}
						}
					}

				}
			}
			//执行完毕
			if($success == 0) $success = false;
			$this->results = $success;
			return $success;
		}

	}

	//取出用户分组group
	public function getGroupList($times = 1){
		$snoopy = new Snoopy();
		$cookie = $this->cookie;
		$token = $this->token;
		//用户管理页面进行采集 用户组信息
		$submit="http://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize=10&pageidx=0&type=0&groupid=0&token=".$token."&lang=zh_CN";
		$snoopy->rawheaders['Cookie'] = $cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$snoopy->fetch($submit);
		preg_match("|groupsList\s+:\s+\((.*?)\)\.groups\,|Uis",$snoopy->results,$grouplist);
		//封装为对象数组
		$groups = json_decode($grouplist[1],true);
		$groups = $groups['groups'] ;
		if($groups){
			$this->gl = $groups ;
			return $groups ;
		}else{
			if($times <3){ //重新尝试登陆 递归处理
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->getGroupList($times+1);
			}else{
				$this->gl = false;
				return false ;
			}
		}

	}

	//单页抓取用户 参数： 用户ID 登陆 cookie token 分组ID  第几页 是否检查重复 默认检查  每页显示几条？默认为 100个 ajax输出结果 递归次数
	public function fetchuser($groupid,$p,$much=100,$times=1){
		set_time_limit (0);
		$cookie = $this->cookie;
		$token = $this->token;
		$snoopy = new Snoopy();
		$snoopy->rawheaders['Cookie'] = $cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		//定义返回数组 为对象数组
		$submit="http://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize=".$much."&pageidx=".$p."&type=0&groupid=".$groupid."&token=".$token."&lang=zh_CN";
		$snoopy->fetch($submit);
		preg_match("|friendsList\s+:\s+\((.*?)\)\.contacts\,|Uis",$snoopy->results,$list) ;
		$arrs = json_decode($list[1],true) ;
		$arr = $arrs['contacts'] ;
		if(!is_array($arr)){ //获取不到
			if($times <3){ //小于3次重试
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->fetchuser($groupid,$p,$much,$times+1);
			}else{
				return false;
			}
		}else{
			krsort($arr);
			//返回该分组好友列表
			$this->ul =  $arr ;
			return $arr;
		}

	}

	public function sendImg($fakeId,$appmsgid,$times = 1){
		$cookie = $this->cookie;
		$token	 =	$this->token;
		$send_snoopy = new Snoopy();
		$send_snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$send_snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/singlemsgpage?token=".$token."&fromfakeid=".$fakeId."&msgid=&source=&count=20&t=wxm-singlechat&lang=zh_CN"; //伪装来源页地址 http_referer
		$send_snoopy->rawheaders['Cookie'] = $cookie;
		$send_snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$send_snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$post = array();
		$post['tofakeid'] = $fakeId;
		$post['type'] = 10;
		$post['appmsgid'] = $appmsgid;
		$post['fid'] = $appmsgid;
		$post['token'] = $token;
		$post['error'] = false;
		$post['ajax'] = 1;
		$submit = "http://mp.weixin.qq.com/cgi-bin/singlesend?t=ajax-response";
		$send_snoopy->submit($submit,$post);
		$result = $send_snoopy->results;
		$result	=	json_decode($result,true);
		if($result['ret'] != 0){
			if($times <3){
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->sendImg($fakeId,$appmsgid,$times+1);
			}else{
				return false;
			}
		}else{
			return true;
		}

	}

	#发送图文素材#
	public function sendNews($fakeId,$appmsgid,$times = 1){
		$cookie = $this->cookie;
		$token	 =	$this->token;
		$send_snoopy = new Snoopy();
		$send_snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$send_snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/singlesendpage?t=message/send&action=index&tofakeid=".$fakeId."&token=".$token."&lang=zh_CN"; //伪装来源页地址 http_referer
		$send_snoopy->rawheaders['Cookie'] = $cookie;
		$send_snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$send_snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$post = array();
		$post['tofakeid'] = $fakeId;
		$post['type'] = 10;
		$post['app_id'] = $appmsgid;
		$post['appmsgid'] = $appmsgid;
		$post['token'] = $token;
		$post['imgcode'] = '';
		$post['random'] = '0.'.rand(1000,9999).rand(1000,9999).rand(1000,9999).rand(1000,9999);
		$post['lang'] = 'zh_CN';
		$post['f'] = 'json';
		$post['t'] = 'ajax-response';
		$post['ajax'] = 1;
		$submit = "http://mp.weixin.qq.com/cgi-bin/singlesend";
		$send_snoopy->submit($submit,$post);
		$result = $send_snoopy->results;
		$result	=	json_decode($result,true);
		if($result['ret'] != 0){
			if($times <3){
				$this->login($this->user,$this->pwd); //重新登陆 递归处理
				$this->sendNews($fakeId,$appmsgid,$times+1);
			}else{
				return false;
			}
		}else{
			return true;
		}

	}

	//采集资料 返回资料数组
	public function fetchprofile($times = 1){
		$snoopy = new Snoopy();
		$cookie = $this->cookie;
		$token = $this->token;
		$snoopy->rawheaders['Cookie'] = $cookie ;
		$snoopy->rawheaders["Pragma"] = "no-cache"; //cache 的http头信息
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		//定义返回数组 为对象数组
		$submit="http://mp.weixin.qq.com/cgi-bin/settingpage?t=setting/index&action=index&token=".$token."&lang=zh_CN";
		$snoopy->fetch($submit);
		//进行抓取数据
		$str = $snoopy->results ;
		//取得资料
		preg_match_all("/<div\s+class=\"meta_content\">\s+([\S]*)\s+<\/div>/", $str,$b) ;
		$res['nick'] = trim(strip_tags($b[1][0])) ;
		$res['ghid'] = trim(strip_tags($b[1][1])) ;
		$res['username'] = trim(strip_tags($b[1][2])) ;
		$res['verifyInfo'] = trim(strip_tags($b[1][3])) ;
		//$res['nick'] = trim(strip_tags($b[1][4])) ;
		$res['signature'] = trim(strip_tags($b[1][5])) ;

		//获取城市信息
		preg_match("/cgiData\s+=([\s\S]*);\s+seajs/", $str,$b) ;
		preg_match("/country:\s+'(.*)'/",$b[1],$country);  //$country[1] //中国
		preg_match("/province:\s+'(.*)'/",$b[1],$province);  //$province[1] //河南
		preg_match("/city:\s+'(.*)'/",$b[1],$city);  //$city[1] //郑州
		$res['country'] = $country[1] ;
		$res['province'] = $province[1] ;
		$res['city'] = $city[1] ;
		//获取fakeid
		preg_match('/fakeid=([\d]+)/', $str,$fake) ;
		$res['fakeid'] = $fake[1] ;
		if($res['fakeid'] == ''){
			if($times < 3){
				$this->login();
				$this->fetchprofile($times+1);
			}else{
				$this->profile = false;
				return false;
			}
		}else{
			$this->profile = $res ;
			return true ;
		}
	}

	//切换模式 默认开发者 开启 $t 1编辑模式 2开发者模式 ， $m 1开启 0关闭
	public function switchmode($t = 2,$m = 1,$times = 1){
		$snoopy = new Snoopy();
		$submit = 'http://mp.weixin.qq.com/misc/skeyform?form=advancedswitchform&lang=zh_CN';
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->referer = "http://mp.weixin.qq.com/advanced/advanced?action=edit&t=advanced/edit&token=".$this->token."&lang=zh_CN"; //伪装来源页地址 http_referer
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache";
		$snoopy->rawheaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
		$snoopy->rawheaders["X-Requested-With"] = "XMLHttpRequest";
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$post = array();
		$post['flag'] = $m;
		$post['token'] = $this->token;
		$post['type'] = $t;
		$snoopy->submit($submit,$post);
		$result = $snoopy->results;
		$this->results = $result;
		if(strlen($result) > 50){
			return true;//成功更改
		}else{
			if($times < 3){
				$this->login();//重新登陆
				$this->switchmode($t,$m,$times+1);
			}else{
				return false;
			}
		}
	}

	//配置接口参数
	public function setapi($url,$token,$times = 1){
		$this->switchmode(1,0); //首先关闭编辑者模式
		$snoopy = new Snoopy();
		$submit = 'http://mp.weixin.qq.com/advanced/callbackprofile?t=ajax-response&lang=zh_CN&token='.$this->token;
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->referer = "http://mp.weixin.qq.com/advanced/advanced?action=interface&t=advanced/interface&token=".$this->token."&lang=zh_CN"; //伪装来源页地址 http_referer
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache";
		$snoopy->rawheaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
		$snoopy->rawheaders["X-Requested-With"] = "XMLHttpRequest";
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$post = array();
		$post['url'] = $url;
		$post['callback_token'] = $token;
		$snoopy->submit($submit,$post);
		$result = $snoopy->results;
		$result = json_decode($result,true); //解析结果
		if($result['ret'] == 0){
			$this->switchmode(2,1); //开启开发者模式
			$this->results = true;
			return true;//成功更改 {"ret":"0", "msg":"2088755568"}
		}else{
			// ret = -302 verify token fail
			if($times < 2){
				$this->login();//重新登陆
				$this->setapi($url,$token,$times+1);
			}else{
				$this->results = $result['msg']; //错误消息
				return $result['msg'];
			}
		}
	}


	//获取用户资料
	public function userinfo($fakeid,$time = 1){
		$snoopy = new Snoopy();
		$submit = 'http://mp.weixin.qq.com/cgi-bin/getcontactinfo';
		$snoopy->agent = "(Mozilla/5.0 (Windows NT 5.1; rv:19.0) Gecko/20100101 Firefox/19.0)"; //伪装浏览器
		$snoopy->referer = "http://mp.weixin.qq.com/cgi-bin/contactmanage?t=user/index&pagesize=10&pageidx=0&type=0&groupid=0&token=".$this->token."&lang=zh_CN";
		$snoopy->rawheaders['Cookie'] = $this->cookie;
		$snoopy->rawheaders["Pragma"] = "no-cache";
		$snoopy->rawheaders['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
		$snoopy->rawheaders["X-Requested-With"] = "XMLHttpRequest";
		$snoopy->rawheaders["Host"] = "mp.weixin.qq.com";
		$post = array();
		$post['token'] = $this->token;
		$post['lang'] = 'zh_CN';$post['random'] = '0.'.rand(100000,888813).'0882017314';
		$post['f'] = 'json';$post['ajax'] = 1;$post['t'] = 'ajax-getcontactinfo';$post['fakeid'] = $fakeid;
		$snoopy->submit($submit,$post);
		$result = $snoopy->results;
		$result = json_decode($result,true); //解析结果
		if($result['base_resp']['err_msg'] == 'ok'){
			$this->results = $result['contact_info'];//返回数组
			return $result['contact_info'];//返回数组
		}else{
			if($times < 3){
				$this->login();//重新登陆
				$this->userinfo($fakeid,$times+1);
			}else{
				$this->results = $result['msg'];
				return $result['msg'];
			}
		}
	}

	/*
	{"fake_id":1852543442,"nick_name":"有缘人(*¯︶¯*)","user_name":"renminbi96023",
	"signature ":"善待自己，明天更美好","city":"洛阳","province":"河南","country":"中国","gender":1,"remark_name":"","group_id":0}
	*/


}