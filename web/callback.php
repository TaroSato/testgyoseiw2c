<?php
//error_log("開始します");
date_default_timezone_set('Asia/Tokyo');

//環境変数の取得
$accessToken = getenv('LINE_CHANNEL_ACCESS_TOKEN');
//$classfier = getenv('CLASSFIER');
//$workspace_id = getenv('CVS_WORKSPASE_ID');
$username = getenv('CVS_USERNAME');
$password = getenv('CVS_PASS');
$db_host =  getenv('DB_HOST');
$db_name =  getenv('DB_NAME');
$db_pass =  getenv('DB_PASS');
$db_user =  getenv('DB_USER');
$LTuser = getenv('LT_USER');
$LTpass = getenv('LT_PASS');


//ユーザーからのメッセージ取得
$json_string = file_get_contents('php://input');
$jsonObj = json_decode($json_string);

$type = $jsonObj->{"events"}[0]->{"message"}->{"type"};
$eventType = $jsonObj->{"events"}[0]->{"type"};
//メッセージ取得
$text = $jsonObj->{"events"}[0]->{"message"}->{"text"};
$Utext = $text;
//ReplyToken取得
$replyToken = $jsonObj->{"events"}[0]->{"replyToken"};
//ユーザーID取得
$userID = $jsonObj->{"events"}[0]->{"source"}->{"userId"};
//返信メッセージ
$resmess = "";

//DB接続
$conn = "host=".$db_host." dbname=".$db_name." user=".$db_user." password=".$db_pass;
$link = pg_connect($conn);

//LT問い合わせ
/*
$bl_isNumeric = false;
$Ltext = $text;

if (is_numeric($Ltext)) {
	$bl_isNumeric = true;
	if ($link) {
		$result = pg_query("SELECT contents FROM botlog WHERE userid = '{$userID}' ORDER BY no DESC");
		while ($row = pg_fetch_row($result)) {
			if(!is_numeric($row[0])){
				$Ltext= $row[0];
				break;
			}
		}
	}
}

$jsonString = callWatsonLT1();
$json = json_decode($jsonString, true);
$language = $json["languages"][0]["language"];
error_log($language);

//日本語以外の場合は日本語に翻訳
if(!$bl_isNumeric){
	if($language != "ja"){
		$data = array('text' => $text, 'source' => $language, 'target' => 'ja');
		$text = callWatsonLT2();
		if($text == ""){
			$text = $Utext;
			$language = "ja";
		}
	}
}
*/

//メッセージ以外のときは何も返さず終了
if($type != "text"){
	exit;
}

$cid = "";
if($eventType == "postback"){
	$cid= $jsonObj->{"events"}[0]->{"postback"}->{"data"};
}

//$url = "https://gateway.watson-j.jp/natural-language-classifier/api/v1/classifiers/".$classfier."/classify?text=".$text;
//$url = "https://gateway.watson-j.jp/natural-language-classifier/api/v1/classifiers/".$classfier."/classify";
//$url = "https://gateway.watsonplatform.net/conversation/api/v1/workspaces/".$workspace_id."/message?version=2017-04-21";
$url = "https://tomcat-w2c-sample-front-gyosei.mybluemix.net/w2c_classifier/api/webchat";
//$url = "http://tomcat-w2c-sample-front-gyosei.mybluemix.net/w2c_classifier/api/yamato";

//$data = array("text" => $text);
//$data = array('input' => array("text" => $text));

$tdate = date("YmdHis");
$sid = "";
$bl_sflg = false;
if ($link) {
	$result = pg_query("SELECT * FROM cvsdata WHERE userid = '{$userID}'");
	if (pg_num_rows($result) == 0) {
		error_log("データなし");
		$bl_sflg = false;
		$result_flag = pg_query($sql);
	}else{
		error_log("データあり");
		$bl_sflg = true;
		$row = pg_fetch_row($result);
		$sid= $row[1];
		$conversation_time= $row[3];
		$timelag = $tdate - $conversation_time;
		if($timelag > 1000){
			$sid = "";
			$cid = "";
		}
	}
}

$data = array("api_version" => "", "session_id" => $sid, "choice_id" => $cid, "message" => $text);

$jsonString = callWatson();

//error_log($jsonString);
$json = json_decode($jsonString, true);
$sid = $json["session_id"];

$resmess = $json["answer"]["text"];
$last_reply = $json["answer"]["last_reply_flg"];
if($resmess== ""){
	$resmess = "市町村を選択してください";
}

error_log("CODE:".$json["result"]["code"]);
error_log("MES:".$json["result"]["message"]);
error_log("last_reply:".$last_reply);

/*
//日本語以外の場合は翻訳
if($language != "ja"){
	$data = array('text' => $resmess, 'source' => 'ja', 'target' => $language);
	$resmess = callWatsonLT2();
}
//改行コードを置き換え
$resmess = str_replace("\\n","\n",$resmess);

$response_format_text = [
    "type" => "text",
	"text" => $resmess
];
*/

if($last_reply == 1){
	$resmess = "以下のURLをご覧ください\n".$resmess;
	$response_format_text = [
			"type" => "text",
			"text" => $resmess
	];
}else{
	error_log("要素数:".count($json["answer"]["choices"]));
	if(count($json["answer"]["choices"]) == 1){
		$response_format_text = [
				"type" => "template",
				"altText" => "this is a buttons template",
				"template" => [
						"type" => "buttons",
						"text" => $resmess,
						"actions" => [
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][0]["label"],
										"data" => $json["answer"]["choices"][0]["id"]
								]
						]
				]
		];
	}
	if(count($json["answer"]["choices"]) == 2){
		$response_format_text = [
				"type" => "template",
				"altText" => "this is a buttons template",
				"template" => [
						"type" => "buttons",
						"text" => $resmess,
						"actions" => [
								[
										"type" => "postback",
										//"label" => $json["answer"]["choices"][0]["label"],
										"label" => "枚方市",
										//"data" => $json["answer"]["choices"][0]["id"]
										"data" => "85"
								],
								[
										"type" => "postback",
										//"label" => $json["answer"]["choices"][1]["label"],
										//"data" => $json["answer"]["choices"][1]["id"]
										"label" => "大和市",
										"data" => "29"
								]
						]
				]
		];
		error_log("label1:".$json["answer"]["choices"][0]["label"]);
		error_log("id1:".$json["answer"]["choices"][0]["id"]);
		error_log("label2:".$json["answer"]["choices"][1]["label"]);
		error_log("id2:".$json["answer"]["choices"][1]["id"]);
	}
	if(count($json["answer"]["choices"]) == 3){
		$response_format_text = [
				"type" => "template",
				"altText" => "this is a buttons template",
				"template" => [
						"type" => "buttons",
						"text" => $resmess,
						"actions" => [
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][0]["label"],
										"data" => $json["answer"]["choices"][0]["id"]
								],
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][1]["label"],
										"data" => $json["answer"]["choices"][1]["id"]
								],
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][2]["label"],
										"data" => $json["answer"]["choices"][2]["id"]
								]
						]
				]
		];
	}
	if(count($json["answer"]["choices"]) > 3){
		$response_format_text = [
				"type" => "template",
				"altText" => "this is a buttons template",
				"template" => [
						"type" => "buttons",
						"text" => $resmess,
						"actions" => [
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][0]["label"],
										"data" => $json["answer"]["choices"][0]["id"]
								],
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][1]["label"],
										"data" => $json["answer"]["choices"][1]["id"]
								],
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][2]["label"],
										"data" => $json["answer"]["choices"][2]["id"]
								],
								[
										"type" => "postback",
										"label" => $json["answer"]["choices"][3]["label"],
										"data" => $json["answer"]["choices"][3]["id"]
								]
						]
				]
		];
	}

}

lineSend:
$post_data = [
	"replyToken" => $replyToken,
	"messages" => [$response_format_text]
	];

$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
    ));
$result = curl_exec($ch);
curl_close($ch);


if (!$link) {
	error_log("接続失敗です。".pg_last_error());
}else{

	if (!$bl_sflg) {
		$sql = "INSERT INTO cvsdata (userid, conversationid, dnode, time) VALUES ('{$userID}','{$sid}','','{$tdate}')";
		$result_flag = pg_query($sql);
	}else{
		$sql = "UPDATE cvsdata SET conversationid = '{$sid}', dnode = '', time = '{$tdate}' WHERE userid = '{$userID}'";
		$result_flag = pg_query($sql);
	}
	if (!$result_flag) {
		error_log("更新に失敗しました。".pg_last_error());
	}
}


function callWatson(){
	global $curl, $url, $username, $password, $data, $options;
	$curl = curl_init($url);

	$options = array(
			CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
			),
			CURLOPT_USERPWD => $username . ':' . $password,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_RETURNTRANSFER => true,
	);

	curl_setopt_array($curl, $options);
	$json = curl_exec($curl);
	//error_log("CURLのエラー".curl_error($curl));
	return $json;
}

function callWatsonLT1(){
	global $curl, $LTuser, $LTpass, $Ltext, $options;
	$curl = curl_init("https://gateway.watsonplatform.net/language-translator/api/v2/identify");

	$options = array(
			CURLOPT_HTTPHEADER => array(
					'content-type: text/plain','accept: application/json'
			),
			CURLOPT_USERPWD => $LTuser. ':' . $LTpass,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $Ltext,
			CURLOPT_RETURNTRANSFER => true,
	);

	curl_setopt_array($curl, $options);
	return curl_exec($curl);
}

function callWatsonLT2(){
	global $curl, $LTuser, $LTpass, $Ltext, $data, $options;
	$curl = curl_init("https://gateway.watsonplatform.net/language-translator/api/v2/translate");

	$options = array(
			CURLOPT_HTTPHEADER => array(
					'content-type: application/json','accept: application/json'
			),
			CURLOPT_USERPWD => $LTuser. ':' . $LTpass,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_RETURNTRANSFER => true,
	);

	curl_setopt_array($curl, $options);
	$jsonString= curl_exec($curl);
	$json = json_decode($jsonString, true);
	return $json["translations"][0]["translation"];
}
