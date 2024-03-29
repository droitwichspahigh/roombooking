<?php
namespace Roombooking;

class Room
{
    protected $id;
    protected $name;
    protected $isBookable;
    protected $feature;
    protected $academicCalendarId;
    protected $schoolCalendarId;
    protected $timetableEntries = [];
    protected $studentCapacity = null;

    public function __construct(int $id, string $name, bool $isBookable = false, string $feature = "")
    {
        $this->id = $id;
        $this->name = $name;
        $this->isBookable = $isBookable;
        $this->feature = $feature;
        
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
    
    public function isBookable() {
        return ($this->isBookable);
    }
    
    public function getAcademicCalendarId() {
        return $this->academicCalendarId;
    }
    
    public function getSchoolCalendarId() {
        return $this->schoolCalendarId;
    }
    
    public function getCapacity() {
        return $this->studentCapacity;
    }
    
    public function getFeature() {
        return $this->feature;
    }
    
    public function setCapacity($capacity) {
        if (!empty($capacity)) {
            $this->studentCapacity = $capacity;
        }
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
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period && $date >= date('Y-m-d')) {
                    $conflict = [0 => $lesson, 1 => $e,];
                    if (isset($_SESSION['roomBookingConflict'])) {
                        $alreadyIn = false;
                        foreach ($_SESSION['roomBookingConflict'] as $conflict) {
                            if (in_array($lesson, $conflict)) {
                                $alreadyIn = true;
                            }
                        }
                        if (! $alreadyIn) {
                            array_push($_SESSION['roomBookingConflict'], $conflict);
                        }
                    } else {
                        $_SESSION['roomBookingConflict'] = [$conflict,];
                    }
                }
            }
        }
        $this->timetableEntries[$lesson->getId()] = $lesson;
    }
    
    public function addUnavailability(Unavailability $unavailability) {
        $this->timetableEntries[-$unavailability->getId()] = $unavailability;
    }
    
    public function getEntriesForPeriod(Period $period, string $date, bool $allEntries = false) {
        $ret = [];
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Unavailability) {
                /* First, check Availability */
                if ($e->includes($date, $period)) {
                    array_push($ret, $e);
                }
            } else if ($e instanceOf Lesson) {
                /* Let's look through the lessons */
                if ($e->getDay()->getDate() === $date && $e->getPeriod() === $period) {
                    array_push($ret, $e);
                }
            }
        }
        return $ret;
    }
    
    public function isThereClash(Period $period, string $date) {
        foreach ($this->timetableEntries as $e) {
            if ($e instanceOf Unavailability) {
                /* First, check Availability */
                if ($e->includes($date, $period)) {
                    return $e->getInfo($date);
                }
            } else if ($e instanceOf Lesson) {
                /* Let's look through the lessons */
                if ($e->getDay()->getDate() === $date) {
                    // We'll make an Unavailability to check for collisions
                    $p = $e->getPeriod();
                    $u = new Unavailability(0, "", strtotime($p->getStartTime()), strtotime($p->getEndTime()));
                    if ($u->includes($date, $period)) {
                        return $e->getInfo();
                    }
                }
            }
        }
        return null;
    }
    
    /**
     * Return the first matching entry
     * @return Event
     */
    public function getEntry(Period $period, string $date) {
        $entries = $this->getEntriesForPeriod($period, $date, true);
        if (!empty($entries)) {
            return $entries[0];
        }
        return null;
    }

    function __destruct()
    {}
}

