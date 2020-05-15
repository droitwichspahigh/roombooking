<?php 

namespace Roombooking;

require "../bin/classes.php";
require "../bin/auth.php";

?>
<!doctype html>
<html>
	<head>
		<?php require "../bin/head.php"; ?>
	</head>
	<body>
		<div class="container">
			<div class="h1">Database tools:</div>
			<div class="row"><a href="database_setup.php" class="btn btn-danger" role="button">Initial database setup- DELETES ALL DATA!</a></div>
		</div>
	</body>
</html>