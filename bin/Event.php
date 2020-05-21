<?php
namespace Roombooking;

abstract class Event
{
    protected $id, $name;
    
    public function __construct()
    {}
    
    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }
    
    public function getInfo() {
        return $this->name;
    }
    
    function __destruct()
    {}
}

