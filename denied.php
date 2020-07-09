<?php
namespace Roombooking;

$skip_auth = true;

require('bin/classes.php');
?>
<html lang="en">

<head>
	<?php include('bin/head.php');?>
</head>

<body class="text-center">

	<div class="card text-center border-danger mx-auto my-5" style="width: 18rem">
		<div class="card-body">
			<a href="./"><img class="mb-4" src="img/times-circle.svg" alt="" width="72" height="72"></a>
			<p class="text-footer text-muted">				
				Access Denied: You are not authorised to use this resource.
				Please contact <a href="mailto:<?= Config::support_email ?>?subject=<?= Config::site; ?>: Access Request">
				support</a> if this is an error.
			</p>
			<p class="text-footer text-muted">You're trying to access this site as <?= $_SERVER['PHP_AUTH_USER'];?></p>
		</div>
	</div>
	
</body>

</html>