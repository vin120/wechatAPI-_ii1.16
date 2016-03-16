<?php

class WechatCallbackapi
{	
	/**
	 * 验证开发者身份
	 */
	public function valid()
	{
		$echoStr = Yii::app()->request->getQuery('echostr');
		if($this->checkSignature()){
			header('content-type:text');
			echo $echoStr;
			exit;
		}
	}
	
	private function checkSignature()
	{
		$signature = Yii::app()->request->getQuery('signature');
		$timestamp = Yii::app()->request->getQuery('timestamp');
		$nonce = Yii::app()->request->getQuery('nonce');
	
		$token = Yii::app()->params['token'];
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr,SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
	
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * 回复消息
	 */
	public function responseMsg()
	{
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

		if (!empty($postStr)){
			$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			$RX_TYPE = trim(strtolower($postObj->MsgType));
	
			switch ($RX_TYPE)
			{
				case "text":
				    $resultStr = $this->receiveText($postObj);
				    break;
				case "event":
					$resultStr = $this->receiveEvent($postObj);
					break;
				default:
					$resultStr = "Unknow msg type: ".$RX_TYPE;
					break;
			}
			echo $resultStr;
		}else {
			echo "";
			exit;
		}
	}
	
	
	/**
	 * 文本消息
	 * @param unknown $object
	 * @return string
	 */
	private function receiveText($object)
	{
		$contentStr = "你发送的内容为：".$object->Content;
		$resultStr = $this->transmitText($object, $contentStr);
		return $resultStr;
	}
	

	/**
	 * 图片消息
	 * @param unknown $object
	 */
	private function receiveImage($object)
	{
		$contentStr = "你发送的是图片，地址为：".$object->PicUrl;
		$resultStr = $this->transmitText($object, $contentStr);
		return $resultStr;
	}

	/**
		 1、click：点击推事件
		 2、view：跳转URL
		 3、scancode_push：扫码推事件
		 4、scancode_waitmsg：扫码推事件且弹出“消息接收中”提示框
		 5、pic_sysphoto：弹出系统拍照发图
		 6、pic_photo_or_album：弹出拍照或者相册发图
		 7、pic_weixin：弹出微信相册发图器
		 8、location_select：弹出地理位置选择器
		 9、media_id：下发消息（除文本消息）
		 10、view_limited：跳转图文消息URL
	 */
	

	/**
	 * 事件消息
	 * @param unknown $object
	 * @return void|string
	 */
	private function receiveEvent($object)
	{
		$contentStr = "";
	
		switch ($object->Event)
		{
			case "subscribe":
				$contentStr = "欢迎您关注都市通网络科技";
				break;
			case "unsubscribe":
				$contentStr = "Good Bye";
				break;
			case "CLICK":
				switch ($object->EventKey)
				{
					case "key1":
						$contentStr = "欢迎，欢迎! \n\n<a href='https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx05a87acdc3d63406&redirect_uri=http://leijiao.cn/test/web/site/index&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect'>请点击链接登录</a>\n\n谢谢大家支持。";
						break;
					case "huiyuandenglu":
						$contentStr[] = array("Title" =>"会员登录",
						"Description" =>"如果您是2015年7月12日前注册的用户，可通过这里进行登录",
						"PicUrl" =>"http://www.zhfiip.cn/img/x5.jpg",
						"Url" =>"http://www.zhfiip.cn/login.php?wx_id=".$object->FromUserName);
						break;
					case "huiyuanzhuce":
						$contentStr[] = array("Title" =>"会员注册",
						"Description" =>"如果您是新的关注用户，可以点击注册成为我们的会员，享受更多超值优惠和礼品！",
						"PicUrl" =>"http://www.zhfiip.cn/img/x6.jpg",
						"Url" =>"http://www.zhfiip.cn/register.php?wx_id=".$object->FromUserName);
						break;
					case "huiyuanzixun":
						$contentStr[] = array("Title" =>"会员资讯",
						"Description" =>"点击查看我的信息",
						"PicUrl" =>"http://www.zhfiip.cn/img/x7.jpg",
						"Url" =>"http://www.zhfiip.cn/member.php?wx_id=".$object->FromUserName);
						break;
					case "wodejifen":
						$contentStr[] = array("Title" =>"我的积分",
						"Description" =>"点击查看我的积分",
						"PicUrl" =>"http://www.zhfiip.cn/img/x8.jpg",
						"Url" =>"http://www.zhfiip.cn/member_jifen.php?wx_id=".$object->FromUserName);
						break;
					case "shangjiaqiandao":
						$contentStr[] = array("Title" =>"商家签到",
						"Description" =>"快来商家网店里签到啦！签到次数可以兑换精美礼品喔",
						"PicUrl" =>"http://www.zhfiip.cn/img/x9.jpg",
						"Url" =>"http://www.zhfiip.cn/shangjia.php?wx_id=".$object->FromUserName);
						break;
	
					default:
						break;
				}
				break;
			default:
				break;
	
		}
		if (is_array($contentStr)){
			$resultStr = $this->transmitNews($object, $contentStr);
		}else{
			$resultStr = $this->transmitText($object, $contentStr);
		}
		return $resultStr;
	}
	
	
	
	/**
	 * 回复文字消息
	 * @param unknown $object
	 * @param unknown $content
	 */
	private function transmitText($object, $content)
	{
		$textTpl = "<xml>
	                    <ToUserName><![CDATA[%s]]></ToUserName>
	                    <FromUserName><![CDATA[%s]]></FromUserName>
	                    <CreateTime>%s</CreateTime>
	                    <MsgType><![CDATA[text]]></MsgType>
	                    <Content><![CDATA[%s]]></Content>
						<FuncFlag>0</FuncFlag>
                    </xml>";
		$resultStr = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
		return $resultStr;
	}
	
	

	/**
	 * 回复图文消息
	 * @param unknown $object
	 * @param unknown $arr_item
	 */
	private function transmitNews($object, $arr_item)
	{
		//首条标题28字，其他标题39字
		if(!is_array($arr_item)) return;
	
		$itemTpl = "<item>
                   		<Title><![CDATA[%s]]></Title>
                      	<Description><![CDATA[%s]]></Description>
                     	<PicUrl><![CDATA[%s]]></PicUrl>
                       	<Url><![CDATA[%s]]></Url>
                 	</item>";
		$item_str = "";
		foreach ($arr_item as $item)
			$item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
	
		$newsTpl = "<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[news]]></MsgType>
						<ArticleCount>%s</ArticleCount>
						<Articles>$item_str</Articles>
					</xml>";

		$resultStr = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($arr_item));
		return $resultStr;
	}
}