<?php
namespace Roombooking;

class Room
{
    protected $name;
    protected $isIctRoom;
    protected $timetableEntries = [];

    public function __construct(string $name, bool $isIctRoom = false)
    {
        $this->name = $name;
        $this->isIctRoom = $isIctRoom;
    }
    
    /**
     * Watch out, this returns Lessons and Unavailabilities!
     * 
     * @return array
     */
    public function getEntries() {
        return ($this->timetableEntries);
    }
    
    public function getName() {
        return ($this->name);
    }
    
    public function isIctRoom() {
        return ($this->isIctRoom);
    }
    
    /**
     * Adds a new Lesson to the Room
     * 
     * @param Lesson $lesson
     */
    public function addLesson(Lesson $lesson) {
        array_push($this->timetableEntries, $lesson);
    }
    
    public function addUnavailability(Unavailability $unavailability) {
        array_push($this->timetableEntries, $unavailability);
    }
    
    public function getEntry(Period $period, string $date) {
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Unavailability) {
                /* First, check Availability */
                if ($e->includes($date, $period)) {
                    return $e;
                }
            } else if ($e instanceOf Lesson) {
                /* Let's look through the lessons */
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period) {
                    return $e;
                }
            }
        }
        return null;
    }

    function __destruct()
    {}
}

