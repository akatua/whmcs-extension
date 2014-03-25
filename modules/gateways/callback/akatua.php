<?php

include("../../../dbconnect.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

$gatewaymodule = "akatua";

$GATEWAY = getGatewayVariables($gatewaymodule);

if (!$GATEWAY["type"]) die("Module Not Activated");

$transid = $_POST["transaction_id"];
$invoiceid = $_POST["invoice"];

$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); //confirm invoice id
checkCbTransID($transid); //check if transaction has not been processed already

$data['transaction_id'] = $transid;
$data['timestamp'] = time();
if ($GATEWAY['test_mode'] == "on") $data['test_mode'] = 1;

$serverurl = "https://secure.akatua.com/api/v1/getTransactionDetails";

$headers[] = "Content-Type: application/json";
$headers[] = "Akatua-Application-ID: ".$GATEWAY['application_id'];
$headers[] = "Akatua-Signature: ".hash_hmac('sha256',json_encode($data),$GATEWAY['application_secret']);

$confirm = make_httprequest("GET",$serverurl,$data,$headers);

$json = json_decode($confirm);

if (empty($json)) logTransaction($GATEWAY["name"],"Empty response from server","Unsuccessful");
if (!isset($json->success)) exit;

$response = $json->response;

$dbinvoice = mysql_fetch_object(mysql_query("SELECT tblinvoices.total AS amount,tblcurrencies.code AS currency FROM tblinvoices LEFT JOIN tblclients ON tblinvoices.userid=tblclients.id LEFT JOIN tblcurrencies ON tblclients.currency=tblcurrencies.id WHERE tblinvoices.id='$invoiceid'"));

//check actual order amount against response amount
if ($dbinvoice->amount != $response->amount) {
  logTransaction($GATEWAY["name"],"Invoice amount and actual amount paid do not match","Unsuccessful");
  exit;
}

if ($response->status == "completed") {
	addInvoicePayment($invoiceid,$response->id,$response->amount,$response->fee,$gatewaymodule);
	logTransaction($GATEWAY["name"],$_POST,"Successful");
}


function make_httprequest($method="GET",$url,$data=array(),$headers=array()) {
	$method = strtoupper($method);
	$json = json_encode($data);

	if (function_exists('curl_version') && strpos(ini_get('disable_functions'),'curl_exec') === false) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$result = curl_exec($ch);
		$error = curl_error($ch);
		if ($error) throw new Exception($error);
		curl_close($ch);
	}
	else {
		$urlbits = parse_url($url);
		$host = $urlbits['host'];
		$path = $urlbits['path'];

		$remote = fsockopen("ssl://{$host}", 443, $errno, $errstr, 30);

		if (!$remote) {
			throw new Exception("$errstr ($errno)");
		}

		$req = "{$method} {$path} HTTP/1.1\r\n";
		$req .= "Host: {$host}\r\n";
		foreach($headers as $header) {
			$req .= $header."\r\n";
		}
		$req .= "Content-Length: ".strlen($json)."\r\n";
		$req .= "Connection: Close\r\n\r\n";
		$req .= $json;
		fwrite($remote, $req);
		$response = '';
		while (!feof($remote)) {
			$response .= fgets($remote, 1024);
		}
		fclose($remote);

		$responsebits = explode("\r\n\r\n", $response, 2);
		$header = isset($responsebits[0]) ? $responsebits[0] : '';
		$result = isset($responsebits[1]) ? $responsebits[1] : '';
	}
	return $result;
}

?>
