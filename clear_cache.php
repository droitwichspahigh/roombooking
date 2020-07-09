<?php

namespace Roombooking;

require "bin/classes.php";

if (!array_search($auth_user, Config::admin_users) {
    header("location: denied.php");
    die();
}

$db = new Database();

$db->dosql("DELETE FROM serialisedCache;");

header('location: index.php');