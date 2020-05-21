<?php
namespace Roombooking;

abstract class Event
{
    protected $name;
    
    public function __construct()
    {}

    public function getName() {
        return $this->name;
    }
    
    public function getInfo() {
        return $this->name;
    }
    
    function __destruct()
    {}
}

