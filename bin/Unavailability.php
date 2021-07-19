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
        $this->id = $id;
        $this->name = $name;
        $this->startTimeStamp = $startTS;
        $this->endTimeStamp = $endTS;
    }
    
    public function getStartTimeStamp() {
        return $this->startTimeStamp;
    }
    
    // How I managed to get two different names here is beyond me :(
    public function getEndTimeStamp() {
        return $this->endTimeStamp;
    }
    
    public function getStartTime() {
        return date('H:i', $this->getStartTimeStamp());
    }
    
    public function getEndTime() {
        return date('H:i', $this->getEndTimeStamp());
    }
    
    public function includes(string $date, Period $period) {
        /* Check if startTime - endTime collide */
        $startTimeStamp = strtotime($date . " " . $period->getStartTime());
        $endTimeStamp = strtotime($date . " " . $period->getEndTime());
        // Does the Unavailability contain the Lesson?
        if (($startTimeStamp < $this->endTimeStamp && $endTimeStamp > $this->startTimeStamp)) {
            return true;
        }
        // Does the Lesson contain the Unavailability?
        if (($startTimeStamp > $this->endTimeStamp && $endTimeStamp < $this->startTimeStamp)) {
            return true;
        }
        // Do they overlap?
        if ((max($this->startTimeStamp, $startTimeStamp)) - min($this->endTimeStamp, $endTimeStamp) < 0) {
            return true;
        }
        // Phew!  Apparently not.
        return false;
    }

    function __destruct()
    {}
}

