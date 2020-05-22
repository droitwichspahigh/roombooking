<?php
namespace Roombooking;

class Staff
{
    protected $name, $calendarId, $id;

    public function __construct(int $id, string $name, int $calendarId = 0)
    {
        $this->id = $id;
        $this->name = $name;
        $this->calendarId = $calendarId;
    }
    
    public function getName() { return $this->name; }

    function __destruct()
    {}
}

