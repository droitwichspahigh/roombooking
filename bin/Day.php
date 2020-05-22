<?php

namespace Roombooking;

class Day {
    protected $isTermDay;
    protected $date;
    
    /**
     * Store a date along with term date status
     * 
     * @param string $date in the format yyyy-mm-dd
     * @param int $isTermDay 1 if term day, otherwise holiday/weekend
     */
    function __construct(string $date, int $isTermDay) {
        $this->date = $date;
        if ($isTermDay === 1) {
            $this->isTermday = true;
        } else {
            $this->isTermDay = false;
        }
    }
    
    function getDate() {
        return $this->date;
    }
    
    public function isTermDay() {
        return $this->isTermDay;
    }
}