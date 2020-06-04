<?php
namespace Roombooking;

class Staff
{
    protected $name, $id;
    protected $calendarId = null;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
    
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }

    public function getCalendarId() {
        if ($this->calendarId != null) {
            return $this->calendarId;
        }
        $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
        $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/staff/" . $this->id);
        $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
            \Arbor\Query\Query::OPERATOR_EQUALS,
            'ACADEMIC');
        $this->calendarId = (\Arbor\Model\Calendar::query($query))[0]->getResourceId();
        return $this->calendarId;
    }
    
    
    function __destruct()
    {}
}

