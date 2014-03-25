<?php

function akatua_config() {
	$configarray = array(
		"FriendlyName" => array("Type" => "System", "Value"=>"Akatua"),
		"test_mode" => array("FriendlyName" => "Test Mode", "Type" => "yesno", "Description" => "Tick this if you're testing",),
		"application_id" => array("FriendlyName" => "Application ID", "Type" => "text", "Size" => "40", "Description" => "Application ID assigned by Akatua",),
		"application_secret" => array("FriendlyName" => "Application Secret", "Type" => "text", "Size" => "40", "Description" => "Application Secret assigned by Akatua",),
		"logo_url" => array("FriendlyName" => "Logo URL", "Type" => "text", "Size" => "40", "Description" => "Logo to be displayed on checkout page",)
	);
	return $configarray;
}

function akatua_link($params) {

	$test = ($params['test_mode'] == "on") ? 1 : 0;
	$amount = $params['amount'];
	$transaction_type = "checkout";
	$description = base64_encode($params['description']);
	$invoice = $params['invoiceid'];
	$fail_url = $params['returnurl'];
	$success_url = $params['returnurl'];
	$callback_url = $params['systemurl']."/modules/gateways/callback/akatua.php";
	if ($params['logo_url']) $logo_url = $params['logo_url'];

	$timestamp = time();
	$signature = hash_hmac('sha256',"{$params['application_id']}:{$description}:{$timestamp}",$params['application_secret']);

	$out = '
	<form method="post" action="https://secure.akatua.com/checkout">
	<input type="hidden" name="test_mode" value="'.$test.'" />
	<input type="hidden" name="application_id" value="'.$params['application_id'].'" />
	<input type="hidden" name="signature" value="'.$signature.'" />
	<input type="hidden" name="timestamp" value="'.$timestamp.'" />
	<input type="hidden" name="transaction_type" value="'.$transaction_type.'" />
	<input type="hidden" name="description" value="'.$description.'" />
	<input type="hidden" name="amount" value="'.$amount.'" />
	<input type="hidden" name="invoice" value="'.$invoice.'" />
	<input type="hidden" name="success_url" value="'.$success_url.'" />
	<input type="hidden" name="fail_url" value="'.$fail_url.'" />
	<input type="hidden" name="callback_url" value="'.$callback_url.'" />
	<input type="hidden" name="logo_url" value="'.$logo_url.'" />
	<input type="image" src="https://secure.akatua.com/images/buttons/checkout_01.png" />
	</form>';
	return $out;
}

?>