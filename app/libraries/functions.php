<?php
//mongoDB查询得到的Cursor转换为数组
function mongoCursorToArray(MongoCursor $cursor){
	$r = [];
	if ($cursor->count() > 0) foreach ($cursor as $v) $r[] = $v;
	return $r;
}
//将查询字段['f1','f2']类型转为mongoDB识别的[f1=>1,f2=>1]
function mongoFormatQueryFields($f = []){
	$r = [];
	if (count($f) > 0) foreach($f as $v) $r[$v] = 1;
	return $r;
}	
//异步发送HTTP请求	
function asyncHttpRequest($options = [], $data = [], $json = false){
	extract(array_merge(['url' => '', 'method' => 'GET', 'header' => false], $options));
	$pathinfo = parse_url($url);
	$host = isset($pathinfo['host']) ? $pathinfo['host'] : '127.0.0.1';
	$port = isset($pathinfo['port']) ? $pathinfo['port'] : 80;
	$path = isset($pathinfo['path']) ? $pathinfo['path'] . '?' . @$pathinfo['query'] : '/';
	$sock = fsockopen($host, $port, $errno, $errstr, 30);
	stream_set_blocking($sock, 0);
	stream_set_timeout($sock, 1);
	$method = strtoupper($method);
	$header = "{$method} {$path} HTTP/1.1\r\n";
	$header.= "Host: {$host}\r\n";
	if (isset($referer)) {
		$header.= "Referer: {$referer}\r\n";
	}
	$header.= "Connection:Close\r\n";
	if ($method == 'POST') {
		if ($json) {
			$header.= "Content-Type: application/json; charset=utf-8\r\n";
			$__data = json_encode($data);				
		} else {
			$header.= "Content-type: application/x-www-form-urlencoded\r\n";
			$__data = http_build_query($data);				
		}
		$header.= "Content-Length:" . strlen($__data) . "\r\n\r\n";
		$header.= $__data;
	}
	$bytes = fwrite($sock, $header);
	usleep(1000);
	fclose($sock);
	return $bytes;
}	
//同步发送HTTP请求	
function syncHttpRequest($options = [], $data = [], $json = false){
	extract(array_merge(['url' => '', 'method' => 'GET', 'header' => false], $options));
	$ch = curl_init($url);		
	if (strtoupper($method) == 'POST') {
		if ($json) {
			$__data = json_encode($data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8', 'Content-Length:' . strlen($__data)]);			
		} else {
			$__data = http_build_query($data);
		}
		curl_setopt($ch, CURLOPT_POST, 1);			
		curl_setopt($ch, CURLOPT_POSTFIELDS, $__data);
	}	
	if (isset($referer)) {
		curl_setopt($ch, CURLOPT_REFERER, $referer);
	}
	if (isset($cookie_file) && is_file($cookie_file)) {
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); 
	}
	curl_setopt($ch, CURLOPT_HEADER, (bool)$header);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:33.0) Gecko/20100101 Firefox/33.0');		
	$ret_result = curl_exec($ch);
	$ret_code 	= curl_getinfo($ch, CURLINFO_HTTP_CODE);  
	curl_close($ch);
	return [$ret_code, $ret_result]; 		
}		
//重构URL函数
function rebulidUrlParams($url, $key, $value){
	@list($ret_file, $params_str) = explode('?', $url);
	parse_str($params_str, $params_arr);
	if ($value === false) {
		unset($params_arr[$key]);
	} else {
		$params_arr[$key] = $value;
	}
	if( ! empty($params_arr)){
		$ret_file = $ret_file . '?' . http_build_query($params_arr);
	}
	return $ret_file; 
}	
//获得微妙时间戳
function getMicrosecond(){
	list($usec, $sec) = explode(" ", microtime());
	return sprintf('%.6f', floatval($sec)+floatval($usec))*1000000;
}
//获取客户端ip
function get_real_ip(){
    static $realip;
    if(isset($_SERVER)){
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $realip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        }else if(isset($_SERVER['HTTP_CLIENT_IP'])){
            $realip=$_SERVER['HTTP_CLIENT_IP'];
        }else{
            $realip=$_SERVER['REMOTE_ADDR'];
        }
    }else{
        if(getenv('HTTP_X_FORWARDED_FOR')){
            $realip=getenv('HTTP_X_FORWARDED_FOR');
        }else if(getenv('HTTP_CLIENT_IP')){
            $realip=getenv('HTTP_CLIENT_IP');
        }else{
            $realip=getenv('REMOTE_ADDR');
        }
    }
    return $realip;
}
//清除emoji表情
function clearEmoji($name){
    $name = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', $name);
    $name = preg_replace('/xE0[x80-x9F][x80-xBF]‘.‘|xED[xA0-xBF][x80-xBF]/S','?', $name);
    return json_decode(preg_replace("#(\\\ud[0-9a-f]{3})#i","",json_encode($name)));
}

function postMsg($postdata=[]){
    $ch = curl_init();
    curl_setopt ($ch, CURLOPT_URL, UTERM_MSG_URL);
    curl_setopt ($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    $result=curl_exec ($ch);
    curl_close ($ch);
    return $result;
}

function getClientIP(){
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $realip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $realip = $_SERVER['REMOTE_ADDR'];
    }
    //如果是代理服务器，有可能返回两个IP,这是取第一个即可
    if (stristr($realip, ',')) {
		$realip = strstr($realip, ',', true);
	}     
    return str_replace('#', '', $realip);	
}