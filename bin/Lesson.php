<?php
namespace Roombooking;

class Lesson
{

    protected $name;
    protected $staff;
    
    public function __construct(string $name, array $staff)
    {
        $this->name = $name;
        $this->staff = $staff;        
    }

    /**
     * Returns staff separated by " & "
     *
     * @return string
     */
    public function strStaff() {
        return implode(" & ", $this->staff);
    }
    
    function __destruct()
    {}
}

