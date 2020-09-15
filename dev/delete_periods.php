<?php
namespace Roombooking;

require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database();

$periods = $db->long_cache_get_array("Roombooking\Period");

if (isset($_GET['period_to_delete'])) {
    foreach ($periods as $k => $p) {
        if ($p->getId() == $_GET['period_to_delete']) {
            unset($periods[$k]);
            break;
        }
    }
    $db->long_cache_clean("Roombooking\Period");
    $db->long_cache_put_array($periods);
    header('Location: delete_periods.php');
}

?>
<!doctype html>
<html>
<head>
<?php require "../bin/head.php"; ?>
	</head>
	<body>
		<div class="container">
			<div class="row h1"><div class="col">Period</div><div class="col">Start time</div><div class="col">Finish time</div></div>
            <?php
            foreach ($periods as $p) {
                print <<< EOF
<a href="?period_to_delete={$p->getId()}">
    <div class="row">
        <div class="col">{$p->getName()}</div>
        <div class="col">{$p->getStartTime()}</div>
        <div class="col">{$p->getEndTime()}</div>
    </div>
</a>
EOF;
            }
            ?>
		</div>
	</body>
</html>