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
    
    public function getEntries() {
        return ($this->timetableEntries);
    }
    
    /**
     * Returns timetable entry lesson staff separated by " & "
     * 
     * @param int $entry Which lesson (arbitrary ID, corresponding to place in array)
     * @return string
     */
    public function getStaff(int $entry) {
        return implode(" & ", $this->timetableEntries[$entry]['staff']);
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
                'period'    =>   $period,
                'date' =>        $date,
                'lesson_name' => $lesson_name,
                'staff' =>       $staff,
            ]
        );
    }
    
    
    public function getLesson(string $period, string $date) {
        /* Let's look through the lessons */
        foreach ($this->timetableEntries as $e) {
            if ($this->timetableEntries['date'] === $date && $this->timetableEntries['period'] === $period) {
                return $e;
            }
        }
        return false;
    }

    function __destruct()
    {}
}

