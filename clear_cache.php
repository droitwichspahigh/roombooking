<?php

namespace Roombooking;

require "bin/classes.php";

$db = new Database();

$db->dosql("DELETE FROM serialisedCache;");

header('location: index.php');