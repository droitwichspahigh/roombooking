<?php

namespace Roombooking;

/* Have we been called before? */
if (!class_exists("Config")) {
    require "Config.php";
    require "Database.php";
    require "GraphQLClient.php";
    require "Room.php";
    require "Site.php";
    require Config::$site_docroot . "/contrib/php-graphql-client/vendor/autoload.php";
    /**
     * We start the session timer on creation, and destroy it after that time.
     * We don't allow keepalive or the data will become stale.
     */
    $timeout_duration = 300;
    
    session_start(['gc_maxlifetime' => $timeout_duration, 'cookie_lifetime' => $timeout_duration]);
    
    $time = $_SERVER['REQUEST_TIME'];
    
    if (isset($_SESSION['SESSION_CREATIONTIME']) &&
        ($time - $_SESSION['SESSION_CREATIONTIME']) > $timeout_duration) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['SESSION_CREATIONTIME'] = $time;
    }
    if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off") {
        header('HTTP/1.1 301 Moved Permanently');
        header('location: ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
}
