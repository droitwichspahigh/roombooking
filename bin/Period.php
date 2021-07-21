<?php
namespace Roombooking;

class Period
{
    protected $id, $name, $startTime, $endTime;    
    
    /**
     * Times must be in the form hh:mm:ss
     * 
     * @param string $name
     * @param string $startTime
     * @param string $endTime
     */
    public function __construct(int $id, string $name, string $startTime, string $endTime)
    {
        $this->id = $id;
        $this->name = $name;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }
    
    public function getId()         { return $this->id; }
    public function getName()       { return $this->name; }
    public function getStartTime($hhmm = false)  { return $hhmm ? substr($this->startTime, 0, 5) : $this->startTime; }
    public function getEndTime($hhmm = false)    { return $hhmm ? substr($this->endTime, 0, 5) : $this->endTime; }

    function __destruct()
    {}
}

