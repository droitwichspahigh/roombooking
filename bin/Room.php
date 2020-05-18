<?php
namespace Roombooking;

class Room
{
    protected $name;
    public $ttfrag = [];

    public function __construct($name)
    {
        $this->name = $name;
    }
    
    public function addTTFrag($data) {
        array_push($this->ttfrag, $data);
    }

    function __destruct()
    {}
}

