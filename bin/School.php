<?php
namespace Roombooking;

class School
{
    /**
     * @var array(\Roombooking\Room) $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $days = [];
    protected $tenWorkingDaysFromNow = 0;
    protected $timetableQuery = [];
    protected $timetablePeriod = [];
    protected $client;
    protected $queryData = null;
    protected $staff = null;
    
    public function __construct()
    {
        $this->client = new GraphQLClient();        
        $this->getTimetables();
    }
    /**
     * 
     * @return \Roombooking\Staff
     */
    public function getCurrentlyLoggedInStaff() {
        if (isset ($_SESSION['currentlyLoggedInStaffId'])) {
            return $this->staff[$_SESSION['currentlyLoggedInStaffId']];
        }
        
        /* TODO Remove this, it should be in auth.php or similar */
        //$auth_user = preg_replace('/@' . Config::site_emaildomain . '/', "", $_SERVER['PHP_AUTH_USER']);
        //$auth_user = 'abbie.young';
        $auth_user = 'henry.allen';
        
        Config::debug("School::getRoomCalendarIds: looking for email");
        $emailAddress = $auth_user . "@" . Config::site_emaildomain;
        $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::EMAIL_ADDRESS);
        $query->addPropertyFilter(\Arbor\Model\EmailAddress::EMAIL_ADDRESS, \Arbor\Query\Query::OPERATOR_EQUALS, $emailAddress);
        $emailAddress = \Arbor\Model\EmailAddress::query($query);
        if (!isset($emailAddress[0])) {
            die("Your email address " . $emailAddress . '@' . Config::$site_emaildomain ." appears unrecognised.");
        }
        if (isset($emailAddress[1])) {
            die("Your email address appears to have more than one owner.  This cannot possibly be right");
        }
        
        $emailAddress = \Arbor\Model\EmailAddress::retrieve($emailAddress[0]->getResourceId());
        
        Config::debug("School::getCalendarIds: query complete");
        
        if ($emailAddress->getEmailAddressOwner()->getResourceType() != \Arbor\Resource\ResourceType::STAFF) {
            die("Your email address " . $emailAddress->getEmailAddress() . " appears not to belong to a member of staff.");
        }
        
        Config::debug("School::getCalendarIds: email found");
        $s = $emailAddress->getEmailAddressOwner()->getProperties();
        
        $this->staff[$s['id']] = new Staff($s['id'], $s['person']->getPreferredFirstName() . " " . $s['person']->getPreferredLastName());
        
        $_SESSION['currentlyLoggedInStaffId'] = $s['id'];
        
        return $this->staff[$s['id']];
    }
    
    /**
     * The order of the Rooms is stable
     *
     * @return \Roombooking\Room[]
     */
    public function getRooms() {
        if (!empty($this->rooms)) {
            return $this->rooms;
        }
        
        if (isset($_SESSION['rooms'])) {
            $this->rooms = $_SESSION['rooms'];
            return $this->rooms;
        }
        
        Config::debug("School::getRooms: no session data, fetching");
        
        /* First, we find out the rooms */
        $result = $this->client->rawQuery(
            '{
                IctRoom: RoomRoomFeature (roomFeature__roomFeatureName: "'. Config::roomFeatureName . '") {
                    room {
                        id
                        roomName
                    }
                }
                AllRoom: Room {
                    id
                    roomName
                }
            }');
        
        foreach ($result->getData()['IctRoom'] as $r) {
            $this->rooms[$r['room']['id']] = new Room($r['room']['id'], $r['room']['roomName'], true);
        }
        
        foreach ($result->getData()['AllRoom'] as $r) {
            if (!array_key_exists($r['id'], $this->rooms)) {
                $this->rooms[$r['id']] = new Room($r['id'], $r['roomName'], false);
            }
        }
        
        /* It is most natural to sort Rooms by name and not ID */
        asort($this->rooms);
        
        $_SESSION['rooms'] = $this->rooms;
        
        return $this->rooms;
    }
    
    /**
     * 
     * @return \Roombooking\Room[]
     */
    public function getIctRooms() {
        $ictRooms = [];
        foreach ($this->getRooms() as $id => $r) {
            if ($r->isIctRoom()) {
                $ictRooms[$id] = $r;
            } 
        }
        return $ictRooms;
    }
    
    /**
     * This is the Big Kahuna of queries, that should get everything we need in one fetch.
     * 
     * Bonus is, we can serialise the Data from it, so it's easy to cache in session.
     *
     * @return array
     */
    public function getQueryData() {
        if (!is_null($this->queryData)) {
            return $this->queryData;
        }
        
        if (isset($_SESSION['School_queryData'])) {
            $this->queryData = $_SESSION['School_queryData'];
            return $this->queryData;
        }
        
        Config::debug("School::getQueryData: no session cache data, requerying");
        
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
        $this->queryData = $this->client->rawQuery('
query {
    CalendarEntryMapping (calendar__id_in: [' . implode(",", $this->getRoomCalendarIds()) . '] startDatetime_after: "' . $lastMidnight . '" startDatetime_before: "' . $end . '") {
        id
        lesson: event {
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
    RoomUnavailability (room__id_in: [' . implode (",", array_keys($this->getIctRooms())). '] ){
        room {
            id
        }
        startDatetime
        endDatetime
        displayName
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
        $_SESSION['School_queryData'] = $this->queryData;
        return $this->queryData;
    }
    
    public function resetQuery() {
        unset ($_SESSION['School_queryData']);
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
        $data = $this->getQueryData();
        
        foreach ($data['TimetablePeriodGrouping'] as $p) {
            /* 
             * XXX This assumes that Periods are the same regardless of day,
             *     so places with special Wednesdays or Fridays for example
             *     aren't going to manage with this!
             *     
             *     Hint: you'll know if this is the case when you connect it
             *     to Arbor and the room booking suite crashes...
             */
            $this->timetablePeriod[$p['timetablePeriods'][0]['startTime']] =
                new Period($p['shortName'], $p['timetablePeriods'][0]['startTime'], $p['timetablePeriods'][0]['endTime']);
        }
        
        /* Sort timetablePeriods by starting time, not when they were entered! */
        ksort($this->timetablePeriod);
        
        $tenDays = 10;
        
        foreach ($data['AcademicCalendarDate'] as $d) {
            $day = new Day(date("Y-m-d", strtotime($d['startDate'])), $d['isGoodSchoolDay']);
            $this->days[$d['id']] = $day;
            if ($d['isGoodSchoolDay']) {
                $tenDays--;
                if ($tenDays == 0) {
                    $this->tenWorkingDaysFromNow = $day;
                }
            }
        }

        foreach ($data['CalendarEntryMapping'] as $d) {
            // First deal with lessons
            if (isset($d['lesson']['location'])) {                
                if (!$this->getRooms()[$d['lesson']['location']['id']]->isIctRoom()) {
                    // This must be a non-ICT room, so a calendared lesson
                    continue;
                }
                
                $staff = [];
                foreach ($d['lesson']['staff'] as $s) {
                    if (!isset($this->staff[$s['id']])) {
                        $this->staff[$s['id']] = new Staff($s['id'], $s['displayName']);                    
                    }
                    $staff[$s['id']] = $this->staff[$s['id']];
                }
                
                $startTime = date("H:i:s",   strtotime($d['lesson']['startDatetime']));
                if (!isset ($this->timetablePeriod[$startTime])) {
                    die ("Hm, no timetable map for $startTime. <pre>ttMap = " . print_r($this->timetablePeriod, true));
                }
                
                $day = $this->getDay($d['lesson']['startDatetime']);
                
                if ($day === null) {
                    die ("Hm, no Day for " . $d['lesson']['startDatetime']);
                }
                
                $this->getRooms()[$d['lesson']['location']['id']]->addLesson(
                    new Lesson($d['lesson']['id'], $d['lesson']['displayName'], $day, $this->timetablePeriod[$startTime], $staff)
                );
            }
        }
        foreach ($data['RoomUnavailability'] as $u) {
            // Then deal with availability
            $this->rooms[$u['room']['id']]->addUnavailability(
                new Unavailability(
                    $u['id'],
                    $u['displayName'],
                    strtotime($u['startDatetime']['date']),
                    strtotime($u['endDatetime']['date'])
                    )
            );
        }
    }
    /**
     *
     * @return int[] Array of calendar IDs.  The room calendars are stored under Room ID.
     */
    function getRoomCalendarIds() {
        Config::debug("School::getCalendarIds: no session record or expired, looking up from Arbor");
        
        $cals = [];
        /* Let's find out which Calendars we need to query- these are the Rooms we should query */
        foreach ($this->getIctRooms() as $r) {
            $cals[$r->getId()] = $r->getCalendarId();
        }
        
        return $cals;
    }

    /**
     * Returns an array of Periods
     * @return array(\Roombooking\Period)
     */
    function getPeriods() {
        return $this->timetablePeriod;
    }
    
    /**
     * Give ten working days away- the cutoff for bookings
     * 
     * @return \Roombooking\Day
     */
    function getTenWorkingDaysFromNow() {
        return $this->tenWorkingDaysFromNow;
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

