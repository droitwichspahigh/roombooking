<?php
namespace Roombooking;

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

if (!isset($_GET['copyfrom'])) {
    die(<<<EOF
<form method="get">
<div>Copy from week beginning: <input type="text" name="copyfrom" placeholder="yyyy-mm-dd"></div>
<div>Copy to &nbsp; week beginning: <input type="text" name="copyto" placeholder="yyyy-mm-dd"></div>
<div><input type="submit" value="Submit"></div>
</form>
EOF);
}

define('allRooms', true);

require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database();

$sourceWeek = strtotime($_GET['copyfrom']);
$targetWeek = strtotime($_GET['copyto']);

$school = new School(date('Y-m-d', $sourceWeek), date('Y-m-d', strtotime("+6 days", $sourceWeek)));

if (!isset($_GET['onlyDoFrom'])) {
    $onlyDoFrom = 0;
} else {
    $onlyDoFrom = $_GET['onlyDoFrom'];
}

$lessons = [];

$mappings = [];
$manual_mappings = [];
$identical = [];

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=mappings.csv");
header("Pragma: no-cache");

for ($i = 0; $i < 5; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", $sourceWeek));
    foreach ($school->getPeriods() as $p) {
        foreach ($school->getRooms() as $rId => $r) {
            $e = $r->getEntry($p, $date);
            if (!is_null($e)) {
                $matches = [];
                if (preg_match('/Year (1[10]):/', $e->getName(), $matches)) {
                    $name = preg_replace('/Physics|Chemistry|Biology|Religious Studies|RelStud|PE( Core)? ?(Male|Fem)/', 'Science', $e->getName());
                    if (!isset($lessons[$name])) {
                        $lessons[$name] = [];
                    }
                    array_push($lessons[$name], [$p, $date, $r->getId(), $r->getName()]);
                    //echo "\"$date\",\"{$p->getName()}\",$rId,\"{$r->getName()}\",\"{$e->getName()}\"\n";
                }
            }
        }
    }
}

unset($_SESSION);

$school = new School(date('Y-m-d', $targetWeek), date('Y-m-d', strtotime("+5 days", $targetWeek)));

for ($i=0; $i<5; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", $sourceWeek));
    $nextweek = date('Y-m-d', strtotime("+$i days", $targetWeek));
    foreach ($school->getPeriods() as $p) {
        foreach ($school->getRooms() as $r) {
            $e = $r->getEntry($p, $nextweek);
            if (!is_null($e)) {
                $matches = [];
                if (preg_match('/Year (1[10]):/', $e->getName(), $matches)) {
                    $changeMapped = false;
                    $name = preg_replace('/Physics|Chemistry|Biology|Religious Studies|RelStud/', 'Science', $e->getName());
                    if (isset($lessons[$name])) {
                        foreach ($lessons[$name] as $lesson) {
                            if ($lesson[0] == $p && $lesson[1] == $date) {
                                //echo "\"$nextweek\",\"{$p->getName()}\",$rId,\"{$r->getName()}\",\"{$lesson[3]}\",\"{$e->getName()}\"\n";
                                if ($r->getId() == $lesson[2]) {
                                    array_push($identical, "\"$nextweek\",\"{$p->getName()}\",\"{$r->getName()}\",\"{$e->getName()}\"");
                                }
                                else {
                                    $db->dosql("INSERT INTO `bulkchanges` (lessonid, newroomid, lessonname, oldroomname, newroomname, date, period) VALUES ('{$e->getId()}','{$lesson[2]}',\"{$e->getName()}\",'{$r->getName()}','{$lesson[3]}','$nextweek','{$p->getName()}');");
                                    array_push($mappings, "\"$nextweek\",\"{$p->getName()}\",\"{$lesson[3]}\",\"{$r->getName()}\",{$lesson[2]},{$e->getId()},\"{$e->getName()}\"");
                                }
                                $changeMapped = true;
                            }
                        }
                    }
                    if (!$changeMapped) {
                        array_push($manual_mappings, "\"$nextweek\",\"{$p->getName()}\",\"{$r->getName()}\",{$e->getId()},\"{$e->getName()}\"");
                        //echo "XXX FAILED TO MAP \"$nextweek\",\"{$p->getName()}\",$rId,\"{$r->getName()}\",\"{$e->getName()}\"\n";
                    }
                }
            }
        }
    }
}


foreach ($mappings as $m) {
    //echo "$m\n";
}

//echo "\n\n********************\nManual mappings (these don't have A/B symmetry):\n********************\n\n";

foreach ($manual_mappings as $m) {
    echo "$m\n";
}
/*
echo "\n\n********************\nDon't touch (these are the same week A and B):\n********************\n\n";

foreach ($identical as $m) {
    echo "$m\n";
}

*/

//echo "<table><tr><th>Date</th><th>Period</th><th>Lesson</th><th>Old room</th><th>New room</th></tr>";
/*
foreach ($mappings as $m) {
    $onlyDoFrom--;
    if ($onlyDoFrom >= 0) {
        continue;
    } else if ($onlyDoFrom < -50) {
        if (!isset($_GET['onlyDoFrom'])) {
            $onlyDoFrom = 0;
        } else {
            $onlyDoFrom = $_GET['onlyDoFrom'];
        }
        die("<a href=\"?copyfrom={$_GET['copyfrom']}&copyto={$_GET['copyto']}&onlyDoFrom=" . ($onlyDoFrom + 50) . "\">Next load</a>");
    }
    
    $mapping = explode(',', $m);
    for ($j=0; $j < 6; $j++) {
        $mapping[$j] = trim($mapping[$j], '"');
    }
    
    $session = \Arbor\Model\Session::retrieve($mapping[5]);
    $session->setLocation(\Arbor\Model\Room::retrieve($mapping[4]));
    $displayName = (new GraphQLClient)->rawQuery("{Session(id: {$mapping[5]}) {displayName}}")->getData()['Session'][0]['displayName'];
    echo "<tr><td>{$mapping[0]}</td><td>{$mapping[1]}</td><td>{$displayName}</td><td>{$mapping[3]}</td><td>{$session->getLocation()->getShortName()}</td></tr>\n";
}
*/