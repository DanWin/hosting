<?php
require('../common.php');

if(!ENABLE_UPGRADES){
	die('Upgrades disabled');
}
if(!COINPAYMENTS_ENABLED){
	die('CoinPayments disabled');
}
if(empty($_SERVER['HTTP_HMAC'])){
	die("No HMAC signature sent");
}
$merchant = $_POST['merchant'] ?? '';
if(empty($merchant)){
	die("No Merchant ID passed");
}
if($merchant !== COINPAYMENTS_MERCHANT_ID){
	die("Invalid Merchant ID");
}
$request = file_get_contents('php://input');
if(empty($request)){
	die("Error reading POST data");
}
$hmac = hash_hmac("sha512", $request, COINPAYMENTS_IPN_SECRET);
if($hmac !== $_SERVER['HTTP_HMAC']){
	die("HMAC signature does not match");
}
$db = get_db_instance();
$status = 0;
if($_POST['status'] < 0){
	$status = -1;
}elseif($_POST['status'] > 0 && $_POST['status'] < 100){
	$status = 1;
}elseif($_POST['status'] >= 100){
	$status = 2;
}
$stmt = $db->prepare('SELECT status FROM payments WHERE txn_id = ?;');
$stmt->execute([$_POST['txn_id']]);
if($tmp = $stmt->fetch(PDO::FETCH_ASSOC)){
	if($status != $tmp['status']){
		$stmt = $db->prepare('UPDATE payments SET status = ? WHERE txn_id = ?;');
		$stmt->execute([$status, $_POST['txn_id']]);
		payment_status_update($_POST['txn_id']);
	}
}
