<?php

namespace Roombooking;

/* Have we been called before? */
if (!class_exists("Config")) {
    if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off") {
        header('HTTP/1.1 301 Moved Permanently');
        header('location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
    require "Config.php";
    // denied.php skips auth
    if (!isset($skip_auth) || ! $skip_auth) {
        require "auth.php";
    }
    require "Day.php";
    require "Event.php";
    require "Database.php";
    require "GraphQLClient.php";
    require "Lesson.php";
    require "Period.php";
    require "Room.php";
    require "School.php";
    require "Staff.php";
    require "Unavailability.php";
    require Config::site_docroot . "/contrib/php-graphql-client/vendor/autoload.php";
    require Config::site_docroot . "/contrib/sis-sdk-php/vendor/autoload.php";

    \Arbor\Model\ModelBase::setDefaultGateway(
        new \Arbor\Api\Gateway\RestGateway(
            Config::arbor['site'],
            Config::arbor['user'],
            Config::arbor['password']
            )
        );
    
    /**
     * We start the session timer on creation, and destroy it after that time.
     * We don't allow keepalive or the data will become stale.
     */
    $timeout_duration = 600;
    
    session_start(['gc_maxlifetime' => $timeout_duration, 'cookie_lifetime' => $timeout_duration]);
    
    $time = $_SERVER['REQUEST_TIME'];
    
    if (!isset($_SESSION['SESSION_CREATIONTIME']) ||
        ($time - $_SESSION['SESSION_CREATIONTIME']) > $timeout_duration ||
        (isset($_GET['session_destroy']) && $_GET['session_destroy'] == $_SESSION['SESSION_CREATIONTIME'])) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['SESSION_CREATIONTIME'] = $time;
    }
}
