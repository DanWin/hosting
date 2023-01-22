<?php
require_once('../common.php');
header('Content-Type: text/html; charset=UTF-8');
$db = get_db_instance();
print_header(_('List of hosted sites'), 'td{padding:5px;}', '_blank');
?>
<h1><?php echo _('Hosting - List of hosted sites'); ?></h1>
<?php
main_menu('list.php');
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=1;');
$count=$stmt->fetch(PDO::FETCH_NUM);
$stmt=$db->query('SELECT COUNT(*) FROM users WHERE public=0;');
$hidden=$stmt->fetch(PDO::FETCH_NUM);
echo '<p>'.sprintf(_('Here is a list of %d public hosted sites (%d sites hidden):'), $count[0], $hidden[0]).'</p>';
echo '<table border="1">';
echo '<tr><td>'._('Onion link').'</td></tr>';
$stmt=$db->query('SELECT onions.onion FROM users INNER JOIN onions ON (onions.user_id=users.id) WHERE users.public=1 ORDER BY onions.onion;');
while($tmp=$stmt->fetch(PDO::FETCH_NUM)){
	echo "<tr><td><a href=\"http://$tmp[0].onion\">$tmp[0].onion</a></td></tr>";
}
?>
</table>
</body></html>
