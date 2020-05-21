<?php
namespace Roombooking;

class Room
{
    protected $name;
    protected $timetableEntries = [];

    public function __construct(string $name)
    {
        $this->name = $name;
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
        /* Let's look through the lessons */
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Lesson) {
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period) {
                    return $e;
                }
            } elseif ($e instanceOf Unavailability) {
                
            }
        }
        /* Now, we need to find out if it's Available */
        return null;
    }

    function __destruct()
    {}
}

