<?php
namespace Roombooking;

use \mysqli;
use \mysqli_result;

/** @var mysqli $conn */
class Database
{
    protected $conn;
    
    /**
     * If firstconnection is true, don't connect to any specific database
     *
     * @param boolean $firstconnection
     */
    function __construct($firstconnection = FALSE) {
        $details = Config::db;
        if ($firstconnection) {
            $this->conn = new mysqli($details['host'], $details['user'], $details['password']);
        } else {
            $this->conn = new mysqli($details['host'], $details['user'], $details['password'], $details['name']);
        }
        
        if ($this->conn->connect_error) {
            die('Connect Error (' . $this->conn->connect_errno . ') '
                . $this->conn->connect_error);
        }
    }
    
    /**
     * Performs an SQL operation.
     *
     * @param string $sqlcmd SQL operation to perform
     * @param boolean $critical If set to FALSE, only warn on failure
     * @return \mysqli_result
     */
    function dosql($sqlcmd, $critical = TRUE) {
        if ($result = $this->conn->query($sqlcmd)) {
            Config::debug("$sqlcmd performed successfully<br /><br />");
        } else {
            if ($critical == TRUE) {
                die ("Error:   $sqlcmd failed: " . $this->conn->error);
            } else {
                Config::debug("Warning: $sqlcmd failed: " . $this->conn->error);
            }
        }
        return $result;
    }
    
    /** Mutex lock, will sleep for 50 seconds by default while attempting to gain lock, or die */
    function lock(String $table) {
        $this->dosql("LOCK TABLES roomchanges WRITE");
    }
    
    function unlock() {
        $this->dosql("UNLOCK TABLES;");
    }
    
    function long_cache_put_array(array $objarr) {
        foreach ($objarr as $o) {
            $this->long_cache_put($o);
        }
    }
    
    function long_cache_put(Object $obj) {
        $class = base64_encode(get_class($obj));
        $data = base64_encode(serialize($obj));
        $subscript = $obj->getId();
        $this->dosql("DELETE FROM serialisedCache WHERE class = '$class' and subscript = $subscript;");
        $this->dosql("INSERT INTO serialisedCache (class, subscript, data) VALUES ('$class', $subscript, '$data');");
    }
    
    function long_cache_get_array(String $classname) {
        $classname = base64_encode($classname);
        $result = $this->dosql("SELECT * FROM serialisedCache WHERE class = '$classname';");
        if ($result->num_rows === 0) {
            return null;
        }
        $ret = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            if (!($ret[$row['subscript']] = unserialize(base64_decode($row['data'], true)))) {
                Config::debug("Database::long_cache_get_array: Invalid long term cache data-- binning and refetching");
                $this->long_cache_clean($classname);
                return null;
            }
        }
        return $ret;
    }
    
    function long_cache_get(String $classname, int $subscript) {
        $classname = base64_encode($classname);
        $result = $this->dosql("SELECT * FROM serialisedCache WHERE class = '$classname' and subscript = $subscript;");
        if ($result->num_rows === 0) {
            return null;
        }
        if ($result->num_rows > 1) {
            Config::debug("Database::long_cache_get: Object collision; $classname [$subscript]");
            $this->long_cache_clean($classname);
            return null;
        }
        $row = $result->fetch_array(MYSQLI_ASSOC);
        if (!($ret = unserialize(base64_decode($row['data'], true)))) {
            Config::debug("Database::long_cache_get: Invalid long term cache data-- binning and refetching");
            $this->long_cache_clean($classname);
            return null;
        }        
        return $ret;
    }
    
    function long_cache_clean(String $classname) {
        $classname = base64_encode($classname);
        $this->dosql("DELETE FROM serialisedCache WHERE class = '$classname';");
    }
}