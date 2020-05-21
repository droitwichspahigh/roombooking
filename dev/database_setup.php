<?php
namespace Roombooking;
require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database(TRUE);
$dbname = Config::$db['name'];

$db->dosql("USE $dbname;", FALSE);

if (Config::$maintenance) {
    $db->dosql("DROP DATABASE $dbname;", FALSE); /* Don't mind if this fails */
    $db->dosql("CREATE DATABASE $dbname;");
    $db->dosql("USE $dbname;");
    $db->dosql("
    CREATE TABLE booking (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lesson_id INT NOT NULL,
        oldroom_id INT NOT NULL,
        newroom_id INT NOT NULL,
        CONSTRAINT pk PRIMARY KEY (id)
    );");
}

?>