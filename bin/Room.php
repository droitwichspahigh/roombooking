<?php
namespace Roombooking;

class Room
{
    const PERIOD = 'period',
          DATE =   'date',
          LESSON = 'lesson';
    
    protected $name;
    protected $timetableEntries = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }
    
    public function getEntries() {
        return ($this->timetableEntries);
    }
    
    public function getName() {
        return ($this->name);
    }
    
    /**
     * Adds a new Lesson to the Room
     * 
     * @param string $period
     * @param string $date
     * @param string $lesson_name
     * @param array $staff
     */
    public function addLesson(string $period, string $date, string $lesson_name, array $staff) {
        array_push(
            $this->timetableEntries, 
            [
                PERIOD => $period,
                DATE =>   $date,
                LESSON => new Lesson($lesson_name, $staff),
            ]
        );
    }
    
    
    public function getEntry(string $period, string $date) {
        /* Let's look through the lessons */
        foreach ($this->timetableEntries as $e) {
            if ($this->timetableEntries[DATE] === $date && $this->timetableEntries[PERIOD] === $period) {
                return $e;
            }
        }
        return null;
    }

    function __destruct()
    {}
}

