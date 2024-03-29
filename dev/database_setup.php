<?php
namespace Roombooking;
require "../bin/classes.php";
require "../bin/auth.php";

$db = new Database(TRUE);
$dbname = Config::db['name'];

$db->dosql("USE $dbname;", FALSE);

if (isset(Config::$maintenance) && Config::$maintenance) {
    $db->dosql("DROP DATABASE $dbname;", FALSE); /* Don't mind if this fails */
    $db->dosql("CREATE DATABASE $dbname;");
    $db->dosql("USE $dbname;");
    $db->dosql("
    CREATE TABLE roomchanges (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lesson_id INT NOT NULL,
        oldroom_id INT NOT NULL,
        booking_calendar INT NOT NULL,
        lessondate TEXT NULL,
        roomname TEXT NULL,
        humanreadable TEXT NULL,
        CONSTRAINT pk PRIMARY KEY (id)
    );");
    $db->dosql("
    CREATE TABLE serialisedCache (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        class TINYTEXT NOT NULL,
        subscript SMALLINT NOT NULL,
        data MEDIUMTEXT NOT NULL,
        CONSTRAINT pk PRIMARY KEY (id)
    );");
    $db->dosql("
    CREATE TABLE settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name TEXT NULL,
        value TEXT NULL,
        CONSTRAINT pk PRIMARY KEY (id)
    );");
    $db->dosql("
    CREATE TABLE debug (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        message MEDIUMTEXT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT pk PRIMARY KEY (id)
    );");    
}

?>