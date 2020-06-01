<?php
namespace Roombooking;

class Staff
{
    protected $name, $id;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
    
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getLesson($date, Period $period) {
        
    }

    function __destruct()
    {}
}

