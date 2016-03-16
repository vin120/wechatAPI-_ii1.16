<?php 

class InterfaceController extends Controller
{
	public $enableCsrfValidation = false;  //Yii机制，防止csrf攻击，不能重复提交多次表单，想要多次提交必须设置成false
	
	public function actionIndex()
	{
		$tokenKey = Yii::app()->params['memcacheKey'];//使用memcache保存的key
		$obj_token = new Wechat();
		$token = $obj_token->GetToken($tokenKey);//获取access_token
		$obj_token->CreateMenu($token,$menu_data);//生成菜单栏
		
		echo $token."<br>";
	
		$obj_wechat = new WechatCallbackapi();
		$echostr = Yii::app()->request->getQuery('echostr');
		if (isset($echostr)) {  //isset(),isempty() 里面的参数必须是变量，不能直接把Yii::$app->request->get('echostr')塞进去
			$obj_wechat->valid(); //申请成为开发者，用于向微信发送验证信息
		}else{
			$obj_wechat->responseMsg();  //处理并回复用户发送过来的消息
		}
	}
}