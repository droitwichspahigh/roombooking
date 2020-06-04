<?php
namespace Roombooking;

class Room
{
    protected $id;
    protected $name;
    protected $isIctRoom;
    protected $timetableEntries = [];

    public function __construct(int $id, string $name, bool $isIctRoom = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->isIctRoom = $isIctRoom;
    }
    
    /**
     * Watch out, this returns Lessons and Unavailabilities!
     * 
     * @return array
     */
    public function getEntries() {
        return ($this->timetableEntries);
    }
    
    public function getId() { return $this->id; }
    
    public function getName() {
        return ($this->name);
    }
    
    public function isIctRoom() {
        return ($this->isIctRoom);
    }
    /**
    * So this is marvellous.
    *
    * If I only use the REST API I'm going to have to make tens of connections-
     * one per calendar entry ('lesson').
     *
     * If I only use the GraphQL API, I can't find a way to get the calendar 'belonging'
     * to a Room, and therefore get thousands of returns on the query, meaning I have
     * a large download and many pages to go through.
     *
     * So... until GraphQL allows Room (id_in: [ ]) { calendarEntryMapping }, I'm going to
     * mix&match.  Yay.
     */
    public function getCalendarId() {
        if (isset($_SESSION['room'][$this->id]['calendarId'])) {
            return $_SESSION['room'][$this->id]['calendarId'];
        }
        $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
        $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/rooms/" . $this->id);
        $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
            \Arbor\Query\Query::OPERATOR_EQUALS,
            'ACADEMIC');
        $_SESSION['room'][$this->id]['calendarId'] = (\Arbor\Model\Calendar::query($query))[0]->getResourceId();
        return $_SESSION['room'][$this->id]['calendarId'];
    }
    
    /**
     * Adds a new Lesson to the Room
     * 
     * @param Lesson $lesson
     */
    public function addLesson(Lesson $lesson) {
        array_push($this->timetableEntries, $lesson);
    }
    
    public function addUnavailability(Unavailability $unavailability) {
        array_push($this->timetableEntries, $unavailability);
    }
    
    public function getEntry(Period $period, string $date) {
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Unavailability) {
                /* First, check Availability */
                if ($e->includes($date, $period)) {
                    return $e;
                }
            } else if ($e instanceOf Lesson) {
                /* Let's look through the lessons */
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period) {
                    return $e;
                }
            }
        }
        return null;
    }

    function __destruct()
    {}
}

