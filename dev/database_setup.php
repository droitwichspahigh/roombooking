<?php
namespace Roombooking;
require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database(TRUE);
$dbname = Config::db['name'];

$db->dosql("USE $dbname;", FALSE);

if (defined("Config::maintenance") && Config::maintenance) {
    $db->dosql("DROP DATABASE $dbname;", FALSE); /* Don't mind if this fails */
    $db->dosql("CREATE DATABASE $dbname;");
    $db->dosql("USE $dbname;");
    $db->dosql("
    CREATE TABLE roomchanges (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lesson_id INT NOT NULL,
        oldroom_id INT NOT NULL,
        booking_calendar INT NOT NULL,
        CONSTRAINT pk PRIMARY KEY (id)
    );");
}

?>