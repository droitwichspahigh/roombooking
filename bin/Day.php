<?php

namespace Roombooking;

class Day {
    protected $isTermDay;
    protected $date;
    
    function __construct($date, $isTermDay) {
        $this->date = $date;
        $this->isTermday = $isTermDay;
    }
}