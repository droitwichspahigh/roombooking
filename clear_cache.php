<?php

namespace Roombooking;

require "bin/classes.php";

if (!in_array($auth_user, Config::admin_users)) {
    header("location: denied.php");
    die();
}

$db = new Database();

session_destroy();

$db->dosql("TRUNCATE serialisedCache;");

header('location: index.php');