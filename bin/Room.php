<?php
namespace Roombooking;

class Room
{
    const PERIOD = 'period',
          DAY =   'day',
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
    public function addLesson(string $period, Day $day, string $lesson_name, array $staff) {
        if (!$day) {
            die ("Somehow, there is no Day for $lesson_name, during $period on unknown date");
        }
        
        array_push(
            $this->timetableEntries, 
            [
                self::PERIOD => $period,
                self::DAY =>   $day,
                self::LESSON => new Lesson($lesson_name, $staff),
            ]
        );
    }
    
    
    public function getEntry(string $period, string $date) {
        /* Let's look through the lessons */
        foreach ($this->timetableEntries as $e) {
            if ($e[self::DAY]->getDate() === $date && $e[self::PERIOD] === $period) {
                return $e;
            }
        }
        return null;
    }

    function __destruct()
    {}
}

