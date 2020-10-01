<?php
namespace Roombooking;

class Room
{
    protected $id;
    protected $name;
    protected $isIctRoom;
    protected $academicCalendarId;
    protected $schoolCalendarId;
    protected $timetableEntries = [];

    public function __construct(int $id, string $name, bool $isIctRoom = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->isIctRoom = $isIctRoom;
        
        /*
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
        $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
        $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/rooms/" . $this->id);
        $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
            \Arbor\Query\Query::OPERATOR_EQUALS,
            'ACADEMIC');
        $this->academicCalendarId = (\Arbor\Model\Calendar::query($query))[0]->getResourceId();
        
        $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
        $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/rooms/" . $this->id);
        $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
            \Arbor\Query\Query::OPERATOR_EQUALS,
            'SCHOOL');
        $this->schoolCalendarId = (\Arbor\Model\Calendar::query($query))[0]->getResourceId();
            
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
    
    public function getAcademicCalendarId() {
        return $this->academicCalendarId;
    }
    
    public function getSchoolCalendarId() {
        return $this->schoolCalendarId;
    }
    
    /**
     * Adds a new Lesson to the Room
     * 
     * @param Lesson $lesson
     */
    public function addLesson(Lesson $lesson) {
        $date = $lesson->getDay()->getDate();
        $period = $lesson->getPeriod();
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Lesson) {
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period) {
                    if (!isset($_SESSION['roomBookingConflict'])) {
                        $_SESSION['roomBookingConflict'] = [];
                    }
                    $alreadyIn = false;
                    foreach ($_SESSION['roomBookingConflict'] as $conflict) {
                        if (in_array($lesson, $conflict)) {
                            $alreadyIn = true;
                        }
                    }
                    if (! $alreadyIn) {
                        array_push($_SESSION['roomBookingConflict'],
                            [0 => $lesson, 1 => $e,]);
                    }
                    if (empty($_SESSION['roomBookingConflict'])) {
                        unset ($_SESSION['roomBookingConflict']);
                    }
                }
            }
        }
        $this->timetableEntries[$lesson->getId()] = $lesson;
    }
    
    public function addUnavailability(Unavailability $unavailability) {
        $this->timetableEntries[-$unavailability->getId()] = $unavailability;
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

