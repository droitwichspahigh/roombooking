<?php
namespace Roombooking;

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

if (!isset($_GET['applyto'])) {
    die(<<<EOF
<form method="get">
<div>Apply to week beginning: <input type="text" name="applyto" placeholder="yyyy-mm-dd"></div>
<div><input type="submit" value="Submit"></div>
</form>
EOF);
}

define('allRooms', true);

require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database();

$targetWeek = strtotime($_GET['applyto']);

$school = new School(date('Y-m-d', $targetWeek), date('Y-m-d', strtotime("+6 days", $targetWeek)));
$csvfile = fopen("manual_changes.csv", "r");
$changes = [];
while ($change = fgetcsv($csvfile))
    $changes[] = $change;

echo "<pre>";
    
unset($_SESSION);

$school = new School(date('Y-m-d', $targetWeek), date('Y-m-d', strtotime("+5 days", $targetWeek)));

for ($i=0; $i<5; $i++) {
    $nextweek = date('Y-m-d', strtotime("+$i days", $targetWeek));
    foreach ($school->getPeriods() as $p) {
        foreach ($school->getRooms() as $r) {
            $e = $r->getEntry($p, $nextweek);
            if (!is_null($e)) {
                
                foreach ($changes as $change) {
                    if ($change[0] == $i && $change[1] == $p->getName() && $change[2] == $r->getName() && $change[4] == $e->getName()) {
                        foreach ($school->getRooms() as $room) {
                            if ($room->getName() == $change[3]) {
                                $db->dosql ("INSERT INTO `bulkchanges` (lessonid, newroomid, lessonname, oldroomname, newroomname, date, period) VALUES ('{$e->getId()}','{$room->getId()}',\"{$e->getName()}\",'{$r->getName()}','{$change[3]}','$nextweek','{$p->getName()}');\n");
                                //echo "{$e->getName()}\n";
                            }
                        }
                    }
                }
            }
        }
    }
}
