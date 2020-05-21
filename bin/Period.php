<?php
namespace Roombooking;

class Period
{
    protected $name, $startTime, $endTime;    
    
    /**
     * Times must be in the form hh:mm:ss
     * 
     * @param string $name
     * @param string $startTime
     * @param string $endTime
     */
    public function __construct(string $name, string $startTime, string $endTime)
    {
        $this->name = $name;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
    }
    
    public function getName()       { return $this->name; }
    public function getStartTime()  { return $this->startTime; }
    public function getEndTime()    { return $this->endTime; }

    function __destruct()
    {}
}

