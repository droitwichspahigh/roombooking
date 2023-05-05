<?php
namespace Roombooking;

if(!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="CSE2K"');
    header('HTTP/1.0 401 Unauthorized');
    die();
}

require "bin/classes.php";
/** @var $auth_user // from auth.php */

global $auth_user;

$db = new Database();

echo "<table><tr><th>Date</th><th>Room</th><th>Description</th></tr>";

foreach ($db->dosql("SELECT `humanreadable`, `roomname`, `lessondate` FROM `roomchanges` ORDER BY `lessondate` DESC, `roomname` DESC, `humanreadable` DESC;")->fetch_all(MYSQLI_ASSOC) as $row) {
    echo "<tr><td>{$row['lessondate']}</td><td>{$row['roomname']}</td><td>{$row['humanreadable']}</td></tr>";
}

echo "</table>";