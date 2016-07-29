<?php

class Wechat
{
	private $_appid;
	private $_secret;
	private $_menu_data;
	private $_nowtime;
	private $_reflashtime;
	
	public function __construct()
	{
		$this->_appid = Yii::app()->params['appid'];				//微信公众号appid
		$this->_secret = Yii::app()->params['secret'];				//微信公众号secret
		$this->_menu_data = Yii::app()->params['menu_data'];		//菜单json格式
		$this->_nowtime = date("Y-m-d H:i:s",time());				//当前时间
		$this->_reflashtime = Yii::app()->params['reflashtime'];	//access_token刷新时间
	}

	
	/***
	 * 获取  全局access_token
	 */
	public function GetToken($key)
	{
		//1.判断数据库中是否存在数据
		if($this->GetTokenCount() > 0){
			//2.sql保存了Token数据，先对比时间是否在reflashtime秒内
			$sql_time = $this->GetTokenTime()['curr_time'];
			if( strtotime($this->_nowtime) - strtotime($sql_time) > $this->_reflashtime ){
				//3.超过了reflashtime，需要重新获取Token  并更新到数据库
				$access_token = $this->GetTokenFromUrl($this->_appid,$this->_secret);
				$this->UpdateToken($access_token, $this->_nowtime);
			}else{
				//没到刷新时间，直接从数据库中获取
				$access_token = $this->GetTokenTime()['access_token'];
			}
		}else{
			//sql没保存Token数据，先通过URL获取，再保存到数据库中
			$access_token = $this->GetTokenFromUrl($this->_appid,$this->_secret);
			$this->InsertToken($access_token, $this->_nowtime);
		}
		return $access_token;
	}
	

	/**
	 * 创建菜单栏
	 * @param unknown $access_token
	 * @param unknown $menu_data
	 */
	public function CreateMenu($access_token,$menu_data)
	{
		$url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;
		$this->https_request($url, $menu_data);
	}
	
	
	
	/**
	 * 根据code获取用户信息
	 */
	public function GetUserInfo($code)
	{
		//1.通过code换取  网页授权access_token
		$data = $this->GetTokenFromCodeUrl($this->_appid, $this->_secret, $code);
		//2.检验  授权凭证（access_token）是否有效
		$message = $this->IsTokenAvaliable($data['access_token'],$data['openid']);
		if($message['errcode'] != 0){
			//3.刷新  网页授权access_token
			$data = $this->ReflashTokenFromUrl($data['openid'], $data['refresh_token']);
			if(isset($data['errcode'])){
				return $data['errcode']." : ".$data['errmsg'];
				exit;
			}
		}
		//4.拉取用户信息(需scope为 snsapi_userinfo)
		$userinfo = $this->GetUserInfoFromToken($data['access_token'], $data['openid']);
		return $userinfo;
	}
	
	

	/**
	 * 根据用户的code授权获取 网页授权接口access_token
	 * @param unknown $appid
	 * @param unknown $secret
	 * @param unknown $code
	 */
	private function GetTokenFromCodeUrl($appid,$secret,$code)
	{
		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		return $jsoninfo;
	}
	
	
	/**  检验  网页授权凭证（access_token） 是否有效
	 * 
	 * 返回值  
	 * 正确时 :{ "errcode":0,"errmsg":"ok"}  
	 * 错误时 :{ "errcode":40003,"errmsg":"invalid openid"}
	 */
	private function IsTokenAvaliable($access_token,$openid)
	{
		$url = "https://api.weixin.qq.com/sns/auth?access_token=$access_token&openid=$openid";
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		return $jsoninfo;
	}
	
	/**   
	 * 刷新  网页授权接口的access_token 
	 * 
	 * 返回值
	 * 正确时:
		 {
		   "access_token":"ACCESS_TOKEN",
		   "expires_in":7200,
		   "refresh_token":"REFRESH_TOKEN",
		   "openid":"OPENID",
		   "scope":"snsapi_userinfo"
		}
		错误时:
		{"errcode":40029,"errmsg":"invalid code"}
	 */
	private function ReflashTokenFromUrl($appid,$reflash_token)
	{
		$url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token=$reflash_token";
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		return $jsoninfo;
	}
	
	
	
	/**  
	 * 根据  网页授权的access_token   获取用户信息
	 * 
	 * 返回值
	 * {
		 "access_token": "ACCESS_TOKEN",
		 "expires_in": 7200,
		 "refresh_token": "REFRESH_TOKEN",
		 "openid": "OPENID",
		 "scope": "snsapi_userinfo,"
	 }
	 */
	private function GetUserInfoFromToken($access_token,$openid)
	{
		$url = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$openid&lang=zh_CN";
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		return $jsoninfo;
	}


	/**
	 * 从网上获取全局access_token
	 * @param unknown $appid
	 * @param unknown $secret
	 */
	public function GetTokenFromUrl($appid,$secret)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret";
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		$access_token = $jsoninfo["access_token"];
		return $access_token;
	}
	
	
	/**
	 * get sql token count 
	 */
	private function GetTokenCount()
	{
		$token_count = Token::find()->count();
		return $token_count;
	}
	
	
	/**
	 * get token and time  from sql
	 */
	private function GetTokenTime()
	{
		$tokentime = Token::find()->where(['id'=>'1'])->one();
		return $tokentime;
	}
	

	/**
	 * Insert access_token to sql 
	 * @param unknown $access_token
	 * @param unknown $nowtime
	 */
	private function InsertToken($access_token,$nowtime)
	{
		$tokenTable = new Token();
		$tokenTable->id = 1;
		$tokenTable->access_token = $access_token;
		$tokenTable->curr_time = $nowtime;
		$tokenTable->save();
	}
	
	
	/**
	 * Update access_token 
	 * @param unknown $access_token
	 * @param unknown $nowtime
	 */
	private function UpdateToken($access_token,$nowtime)
	{
		$tokenTable = Token::find()->where(['id'=>'1'])->one();
		$tokenTable->access_token = $access_token;
		$tokenTable->curr_time = $nowtime;
		$tokenTable->save();
	}
	
	
	private function crul($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	
	
	private function https_request($url,$data)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		if (!empty($data)){
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($curl);
		curl_close($curl);
		return $output;
	}
	


	/**
	 * 	get menu
	 * @param unknown $access_token
	 */
	public function GetMenu($access_token)
	{
		$url="https://api.weixin.qq.com/cgi-bin/menu/get?access_token=".$access_token;
		$output = $this->crul($url);
		return $output;
	}
	
	

	/**
	 * get tencent's  server ip
	 * @param unknown $access_token
	 */
	public function GetServerIp($access_token)
	{
		$url = "https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=".$access_token;
		$output = $this->crul($url);
		$jsoninfo = json_decode($output, true);
		$ip_list = $jsoninfo["ip_list"];
		return $ip_list;
	}
	
	
}