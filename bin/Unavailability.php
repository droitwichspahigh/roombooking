<?php
namespace Roombooking;

class Unavailability extends Event
{
    protected $startTimeStamp, $endTimeStamp;    
    
    /**
     * 
     * @param int       $id
     * @param string    $name
     * @param int       $start
     * @param int       $end
     */
    public function __construct(int $id, string $name, int $startTS, int $endTS)
    {
        $this->name = $name;
        $this->startTimeStamp = $startTS;
        $this->endTimeStamp = $endTS;
    }
    
    public function getstartTimeStamp() {
        return $this->startTimeStamp;
    }
    
    public function getEndDateTime() {
        return $this->startTimeStamp;
    }
    
    public function includes(string $date, Period $period) {
        $ts = strtotime($date . " " . $period->getStartTime());
        if (($ts <= $this->endTimeStamp && $ts >= $this->startTimeStamp)) {
            return true;
        }
        return false;
    }

    function __destruct()
    {}
}

