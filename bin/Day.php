<?php

namespace Roombooking;

class Day {
    protected $isTermDay;
    protected $date;
    
    function __construct($date, $isTermDay) {
        $this->date = $date;
        $this->isTermday = $isTermDay ? true : false;
    }
    
    function getDate() {
        return $this->date;
    }
    
    function isTermDay() {
        return $this->isTermDay;
    }
}