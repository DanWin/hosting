<?php
require('../common.php');
if(!ENABLE_UPGRADES || !COINPAYMENTS_ENABLED){
	header('Location: home.php');
	exit;
}
$user=check_login();
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
header('Content-Type: text/html; charset=UTF-8');
print_header('Upgrade account', 'td{padding:5px;}');
?>
<h1><?php echo _('Hosting - Upgrade account'); ?></h1>
<?php
$rates = coinpayments_get_rates();
if($rates === false){
	echo '<p>'._('An error occurred talking to coinpayments').'</p>';
}else{
?>
	<form action="upgrade.php" method="post">
	<table border="1">
	<tr><td><?php echo _('Desired upgrade'); ?></td><td>
	<select name="upgrade">
	<?php
	foreach(ACCOUNT_UPGRADES as $name => $upgrade){
		echo '<option value="'.htmlspecialchars($name).'"';
		if(isset($_REQUEST['upgrade']) && $name===$_REQUEST['upgrade']){
			echo ' selected';
		}
		echo '>'.htmlspecialchars($upgrade['name']).' ($'.$upgrade['usd_price'].')</option>';
	}
	?>
	</td></tr>
	<tr><td><?php echo _('Desired payment currency'); ?></td><td>
	<select name="currency">
	<?php
	$i=0;
	foreach($rates as $symbol => $rate){
		if($rate['accepted']===1 && in_array('payments', $rate['capabilities'])){
			echo '<option value="'.htmlspecialchars($symbol).'">'.htmlspecialchars($rate['name']).' ('.htmlspecialchars($symbol).')</option>';
		}
	}
	?>
	</select></td></tr>
	<tr><td colspan="2" style="text-align:center"><button type="submit"><?php echo _('Pay now'); ?></button></td></tr>
	</table>
	</form>
<?php
}
if(isset($_POST['currency']) && isset($_POST['upgrade'])){
	if(!isset(ACCOUNT_UPGRADES[$_POST['upgrade']])){
		echo '<p>'._("Sorry, looks like you didn't select a valid upgrade.").'</p>';
	}elseif(!isset($rates[$_POST['currency']]) || $rates[$_POST['currency']]['accepted'] !== 1 || !in_array('payments', $rates[$_POST['currency']]['capabilities'])){
		echo '<p>'._("Sorry, looks like you didn't select a valid payment currency.").'</p>';
	}else{
		$db = get_db_instance();
		$transaction = coinpayments_create_transaction($_POST['currency'], ACCOUNT_UPGRADES[$_POST['upgrade']]['usd_price'], $_POST['upgrade'], $user['id']);
		if($transaction === false){
			echo '<p>'._('An error occurred creating the transaction, please try again').'</p>';
		}else{
			echo '<p>'.sprintf(_('Please pay %1$s to %2$s'), "$transaction[amount] $_POST[currency]", $transaction['address']).'</p>';
			echo '<img src="'.(new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'eccLevel' => QRCode::ECC_H])))->render($transaction['address']).'" alt="'._('QR Code').'">';
			echo '<p>'._('Once paid, it can take a while until the upgrade is applied to your account. Usually within an hour.').'</p>';
		}
	}
}
?>
<p><a href="home.php"><?php echo _('Go back to dashboard'); ?></a></p>
</body>
</html>
