<?php

namespace Roombooking;

require "bin/classes.php";

if (!in_array($auth_user, Config::admin_users)) {
    header("location: denied.php");
    die();
}

$db = new Database();

$db->dosql("DELETE FROM serialisedCache;");

header('location: index.php');