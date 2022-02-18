<?php
namespace Roombooking;

class Settings {
    public const MAINTENANCE = "maintenance";
    public const VERSION = "version";
    
    private static $db = null;
    
    static function getDb() {
        if (is_null(self::$db)) {
            self::$db = new Database();
            $version = self::getSetting(Settings::VERSION);
            if (is_null($version) || $version == "-1") {
                self::$db->dosql("
                    CREATE TABLE settings (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        name TEXT NULL,
                        value TEXT NULL,
                        CONSTRAINT pk PRIMARY KEY (id)
                    );", false);
                self::$db->dosql("
                    ALTER TABLE roomchanges ADD humanreadable TEXT NULL;", false);
                self::storeSetting(Settings::VERSION, "-1");
                if (basename($_SERVER['PHP_SELF']) != 'settings.php')
                    die('<a href="settings.php">Settings are blank.</a>');
            }
        }
        return self::$db;
    }
    
    static function getSetting(String $setting) {
        $result = self::getDb()->dosql("SELECT value FROM settings WHERE name = '$setting';", false);
        if ($result && !is_null($ret = $result->fetch_row())) {
            return $ret[0];
        } else {
            return null;
        }
    }
    
    static function storeSetting(String $setting, String $value) {
        $current = self::getSetting($setting);
        if (is_null($current)) {
            self::getDb()->dosql("INSERT INTO settings (name, value) VALUES ('$setting', '$value');");
        } else {
            self::getDb()->dosql("UPDATE settings SET value = '$value' WHERE name = '$setting'");
        }
    }
}