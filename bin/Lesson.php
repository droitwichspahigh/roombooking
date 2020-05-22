<?php
namespace Roombooking;

class Lesson extends Event
{
    protected $staff;
    protected $day;
    protected $period;
    
    public function __construct(int $id, string $name, Day $day, Period $period, array $staff)
    {
        $this->id = $id;
        $this->name = $name;
        $this->staff = $staff;  
        $this->day = $day;
        $this->period = $period;
    }
    
    public function getDay() { return $this->day; }
    public function getPeriod() { return $this->period; }

    /**
     * Returns staff separated by " & "
     *
     * @return string
     */
    public function strStaff() {
        return implode(" & ", array_map(function (Staff $s) {return $s->getName(); }, $this->staff));
    }
    
    public function getInfo() {
        return $this->name . "<br />" . $this->strStaff();
    }
    
    function __destruct()
    {}
}

