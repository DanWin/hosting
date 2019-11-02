<?php
include_once('../common.php');
header('Content-Type: text/html; charset=UTF-8');
header('X-Accel-Expires: 60');
$db = get_db_instance();
?>
<!DOCTYPE html><html><head>
<title>Daniel's Hosting - List of hosted sites</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="author" content="Daniel Winzen">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="<?php echo CANONICAL_URL . $_SERVER['SCRIPT_NAME']; ?>">
<style>td{padding:5px;}</style>
<base target="_blank">
</head><body>
<h1>Hosting - List of hosted sites</h1>
<p><a href="index.php" target="_self">Info</a> | <a href="register.php" target="_self">Register</a> | <a href="login.php" target="_self">Login</a> | List of hosted sites | <a href="faq.php" target="_self">FAQ</a></p>
<?php
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=1;');
$count=$stmt->fetch(PDO::FETCH_NUM);
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=0;');
$hidden=$stmt->fetch(PDO::FETCH_NUM);
echo "<p>Here is a list of $count[0] public hosted sites ($hidden[0] sites hidden):</p>";
echo '<table border="1">';
echo '<tr><td>Onion link</td></tr>';
$stmt=$db->query('SELECT onions.onion FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE users.public=1 ORDER BY onions.onion;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	echo "<tr><td><a href=\"http://$tmp[0].onion\">$tmp[0].onion</a></td></tr>";
}
?>
</table>
</body></html>
