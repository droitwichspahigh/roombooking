<?php
namespace Roombooking;

class School
{
    /**
     * @var array $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $days = [];
    protected $timetableQuery = [];
    protected $timetablePeriod = [];
    protected $calendarIds;
    protected $client;
    
    public function __construct()
    {
        /* First, we find out which rooms we need */
        $this->client = new GraphQLClient();
        
        $result = $this->client->rawQuery(
            '{ RoomRoomFeature (roomFeature__roomFeatureName: "'. Config::roomFeatureName . '") { room { shortName roomName displayName } } }');
        
        foreach ($result->getData()['RoomRoomFeature'] as $r) {
            $this->rooms[$r['room']['id']] = new Room($r['room']['roomName']);
        }
        
        /* It is most natural to sort Rooms by name and not ID */
        asort($this->rooms);
        
        $this->getTimetables();
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
     * 
     */
    public function getTimetables() {
        /* 
         * XXX We're going to look ahead by five weeks.
         * 
         * The longest holidays (apart from the summer,
         * where you can't normally book in advance) are
         * two weeks, so that makes four weeks in advance
         * max we'll need.  We'll go for five, because
         * bank holidays can mess things up.
         * 
         * Inefficient?  Meh.
         * 
         */
        $end = date('Y-m-d', strtotime('5 weeks'));
        $lastMidnight = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('yesterday'));
        /* You don't want to know how long this query took to construct :( */
        $data = $this->client->rawQuery('
query {
    CalendarEntryMapping (calendar__id_in: [' . implode(",", $this->getCalendarIds()) . '] startDatetime_after: "' . $lastMidnight . '" startDatetime_before: "' . $end . '") {
        id
        event {
            __typename
            ... on Session {
                location {
                    __typename
                    ... on Room {
                        id
                    }
                }
                startDatetime
                endDatetime
                displayName
                staff {
                    displayName
                }
            }
        }
    }
    TimetablePeriodGrouping {
        shortName
        timetablePeriods {
            dayOfWeek
            dayOfCycle
            startTime
            endTime
        }
    }
    AcademicCalendarDate (startDate_after: "' . $yesterday . '" startDate_before: "' . $end . '") {
        startDate
        dayOfTerm
        isGoodSchoolDay
    }
}')->getData();
        
        foreach ($data['TimetablePeriodGrouping'] as $p) {
            /* 
             * XXX This assumes that Periods are the same regardless of day,
             *     so places with special Wednesdays or Fridays for example
             *     aren't going to manage with this! 
             */ 
            $this->timetablePeriod[$p['timetablePeriods'][0]['startTime']] = $p['shortName'];
        }
        
        /* Sort timetablePeriods by starting time, not when they were entered! */
        ksort($this->timetablePeriod);
        
        foreach ($data['AcademicCalendarDate'] as $d) {
            $this->days[$d['id']] = new Day(date("Y-m-d", strtotime($d['startDate'])), $d['isGoodSchoolDay']);
        }
        
        foreach ($data['CalendarEntryMapping'] as $d) {
            if (isset($d['event']['location'])) {
                $staff = [];
                foreach ($d['event']['staff'] as $s) {
                    $staff[$s['id']] = $s['displayName'];
                }
                
                $startTime = date("H:i:s",   strtotime($d['event']['startDatetime']));
                if (!isset ($this->timetablePeriod[$startTime])) {
                    die ("Hm, no timetable map for $startTime. <pre>ttMap = " . print_r($this->timetablePeriod, true));
                }
                
                $day = $this->getDay($d['event']['startDatetime']);
                
                if ($day === null) {
                    die ("Hm, no Day for " . $d['event']['startDatetime']);
                }
                
                $this->rooms[$d['event']['location']['id']]->addLesson(
                    $this->timetablePeriod[$startTime],
                    $this->getDay($d['event']['startDatetime']),
                    $d['event']['displayName'],
                    $staff
                );
            }
        }
    }
    
    function getCalendarIds() {
        if (!empty($this->calendarIds)) {
            return $this->calendarIds;
        }
        
        if (isset($_SESSION['calendarIds'])) {
            Config::debug("School::getCalendarIds: found session record, returning");
            $this->calendarIds = $_SESSION['calendarIds'];
            return $this->calendarIds;
        }
        
        $this->calendarIds = [];
        /* Let's find out which Calendars we need to query */
        foreach (array_keys($this->rooms) as $rId) {
            $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
            $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/rooms/" . $rId);
            $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
                \Arbor\Query\Query::OPERATOR_EQUALS,
                'ACADEMIC');
                array_push($this->calendarIds, (\Arbor\Model\Calendar::query($query))[0]->getResourceId());
        }
        
        $_SESSION['calendarIds'] = $this->calendarIds;
        return $this->calendarIds;        
    }
    
    /**
     * Returns an array of 'start time' => 'period name'
     * @return array
     */
    function getPeriods() {
        return $this->timetablePeriod;
    }
    
    /**
     * The order of the Rooms is stable
     * 
     * @return array(Room)
     */
    function getRooms() {
        return $this->rooms;
    }
    
    /**
     * Returns a Day based on the date provided
     * 
     * @param string $datetime
     * @return Day
     */
    function getDay(string $datetime) {
        $date = date("Y-m-d", strtotime($datetime));
        foreach ($this->days as $d) {
            if ($date === $d->getDate()) {
                return $d;
            }
        }
        return null;
    }

    function __destruct()
    {}
}

