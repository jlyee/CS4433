<!DOCTYPE html>
<html>
<head>
	<title>Relation: diner</title>
	<link rel="stylesheet" href="../css/default.css">
</head>
<body>
	<ul class='nav-bar'>
		<li><a href="../index.html">Home</a></li>
	</ul>
	<?php
		require_once('utility.php');
		$db = new dbconnection();
		$result = $db->get_table("diner");
		if($result) {
			util::to_html_table(util::active_diner_v_headings(), $result);
		} else {
			echo '<p>Query failed</p>';
		}
	?>
</body>
</html>
