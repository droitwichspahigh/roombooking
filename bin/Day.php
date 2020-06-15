<?php

namespace Roombooking;

class Day {
    protected $isTermDay;
    protected $date;
    protected $id;
    
    /**
     * Store a date along with term date status
     * 
     * @param string $date in the format yyyy-mm-dd
     * @param int $isTermDay 1 if term day, otherwise holiday/weekend
     */
    function __construct(int $id, string $date, int $isTermDay) {
        $this->id = $id;
        $this->date = $date;
        if ($isTermDay === 1) {
            $this->isTermDay = true;
        } else {
            $this->isTermDay = false;
        }
    }
    
    /**
     * Returns the string representation of the date as Y-m-d
     * 
     * @return string
     */
    function getDate() {
        return $this->date;
    }
    
    public function isTermDay() {
        return $this->isTermDay;
    }
    
    public function getId() {
        return $this->id;
    }
}