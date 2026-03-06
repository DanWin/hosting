<?php
require_once('../common.php');
header('Content-Type: text/html; charset=UTF-8');
$db = get_db_instance();
print_header(_('List of hosted sites'), 'td{padding:5px;}', '_blank');
$show_desc = (defined('PUB_ONION_DESC') && (string)PUB_ONION_DESC === '1');
?>
<h1><?php echo _('Hosting - List of hosted sites'); ?></h1>
<?php
main_menu('list.php');
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=1;');
$count=$stmt->fetch(PDO::FETCH_NUM);
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=0;');
$hidden=$stmt->fetch(PDO::FETCH_NUM);
echo '<p>'.sprintf(_('Here is a list of %1$d public hosted sites (%2$d sites hidden):'), $count[0], $hidden[0]).'</p>';
echo '<table border="1">';
echo '<tr><td>'._('Onion link').'</td>';
if ($show_desc) {
	echo '<td>'._('Description').'</td>';
}
echo '</tr>';
// description only when PUB_ONION_DESC = 1 in common.php
if ($show_desc) {
	$stmt=$db->query('SELECT onions.onion, onions.description FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE users.public=1 ORDER BY onions.onion;');
	while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
		$onion = $row['onion'];
		$desc  = htmlspecialchars($row['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		echo "<tr><td><a href=\"http://$onion.onion\">$onion.onion</a></td><td>$desc</td></tr>";
	}
} else {
	$stmt=$db->query('SELECT onions.onion FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE users.public=1 ORDER BY onions.onion;');
	while($row=$stmt->fetch(PDO::FETCH_NUM)){
		$onion = $row[0];
		echo "<tr><td><a href=\"http://$onion.onion\">$onion.onion</a></td></tr>";
	}
}
echo '</table>';
?>
</body></html>
