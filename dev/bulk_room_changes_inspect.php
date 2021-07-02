<?php
namespace Roombooking;

ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

require "../bin/classes.php";
require "../bin/auth.php";
require "../bin/head.php";

$db = new Database();

echo "<table border=\"1\"><tr><th>id</th><th>Date</th><th>Period</th><th>Lesson ID</th><th>New room ID</th><th>Lesson name</th><th>Old room</th><th>New room</th></tr>";

$result = $db->dosql("SELECT * FROM bulkchanges;");

echo "<div class=\"container\"><div><a class=\"btn btn-danger\" href=\"?doit=1\">Go for it!</a>  Might need a few goes as it'll probably time out.</div>";

while ($row = $result->fetch_row()) {
    if (isset($_GET['doit'])) {
        $session = \Arbor\Model\Session::retrieve($row[3]);
        $newRoom = \Arbor\Model\Room::retrieve($row[4]);
        
        $session->setLocation($newRoom);
        $session->save();
        $db->dosql("DELETE FROM bulkchanges WHERE id={$row[0]}");
    } else {
        echo "<tr><td>";
        echo implode("</td><td>", $row);
        echo "</td></tr>";
    }
}
echo "Done.</div>";