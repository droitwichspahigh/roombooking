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
    
    /**
     * @return \Roombooking\Day
     */
    public function getDay() { return $this->day; }
    
    /**
     * @return \Roombooking\Period
     */
    public function getPeriod() { return $this->period; }
    
    /**
     * 
     * @return \Roombooking\Staff
     */
    public function getStaff() { return $this->staff; }

    /**
     * Returns staff separated by " & "
     *
     * @return string
     */
    public function strStaff() {
        return implode(" & ", array_map(function (Staff $s) {return $s->getName(); }, $this->staff));
    }
    
    public function getInfo() {
        $nameSections = explode(':', $this->name);
        $name = end($nameSections);
        return "$name<br />{$this->strStaff()}";
    }
    
    function __destruct()
    {}
}

