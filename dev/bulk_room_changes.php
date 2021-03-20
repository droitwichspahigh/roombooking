<?php
namespace Roombooking;

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

define('allRooms', true);

require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database(TRUE);

$school = new School();

$startDate = strtotime("2021-03-15");

//echo "<pre>";

$lessons = [];

$mappings = [];
$manual_mappings = [];
$identical = [];

//header("Content-type: text/csv");
//header("Content-Disposition: attachment; filename=mappings.csv");
//header("Pragma: no-cache");

echo "\"Week B date\",\"Period\",\"A room\",\"B room\",\"A room ID\",\"Wk B Lesson ID\",\"Lesson\"\n";

for ($i = 0; $i < 5; $i++) {
    $date = date('Y-m-d', strtotime("+$i days", $startDate));
    $nextweek = date('Y-m-d', strtotime("+" . $i+7 . "days", $startDate));
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
                                    array_push($mappings, "\"$nextweek\",\"{$p->getName()}\",\"{$lesson[3]}\",\"{$r->getName()}\",{$lesson[2]},{$e->getId()},\"{$e->getName()}\"");
                                }
                                $changeMapped = true;
                            }
                        }
                    }
                    if (!$changeMapped) {
                        array_push($manual_mappings, "\"$nextweek\",\"{$p->getName()}\",\"{$r->getName()}\",\"{$e->getName()}\"");
                        //echo "XXX FAILED TO MAP \"$nextweek\",\"{$p->getName()}\",$rId,\"{$r->getName()}\",\"{$e->getName()}\"\n";
                    }
                }
            }
        }
    }
}

foreach ($mappings as $m) {
    echo "$m\n";
}

echo "\n\n********************\nManual mappings (these don't have A/B symmetry):\n********************\n\n";

foreach ($manual_mappings as $m) {
    echo "$m\n";
}

echo "\n\n********************\nDon't touch (these are the same week A and B):\n********************\n\n";

foreach ($identical as $m) {
    echo "$m\n";
}
