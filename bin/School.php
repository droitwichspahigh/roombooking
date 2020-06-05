<?php
namespace Roombooking;

class School
{
    /**
     * @var array(\Roombooking\Room) $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $timetableQuery = [];
    protected $timetablePeriod = [];
    protected $client, $db;
    protected $staff = null;
    
    public function __construct()
    {
        $this->client = new GraphQLClient();
        $this->db = new Database();
        $this->getTimetables();
    }
    
    /*
     * 
     * This is the NON-VOLATILE section.  This stuff typically only changes
     * at the beginning of the year or when rooms are converted for use.
     * 
     * We store this in the database cache
     * 
     * 
     */

    /**
     *
     * @return int[] Array of calendar IDs.  The room calendars are stored under Room ID.
     */
    function getRoomCalendarIds() {
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
     * Give ten working days away- the cutoff for bookings.  This needs to be dynamic if caching.
     *
     * @return \Roombooking\Day
     */
    function getTenWorkingDaysFromNow() {
        $tenDays = 10;
        foreach ($this->getDays() as $d) {
            if ($d->isTermDay()) {
                $tenDays--;
                if ($tenDays == 0) {
                    return $d;
                }
            }
        }
        die ("There is something definitely wrong with the cache- can't get ten days ahead.");
    }
    
    /**
     * 
     * @return \Roombooking\Day[]
     */
    function getDays() {
        if (!isset($_SESSION['days'])) {
            foreach ($this->getQueryData()['AcademicCalendarDate'] as $d) {
                $day = new Day(date("Y-m-d", strtotime($d['startDate'])), $d['isGoodSchoolDay']);
                $_SESSION['days'][$d['id']] = $day;
            }
        }
        return $_SESSION['days'];
    }
    
    /**
     * Returns a Day based on the date provided
     *
     * @param string $datetime
     * @return \Roombooking\Day
     */
    function getDay(string $datetime) {
        $date = date("Y-m-d", strtotime($datetime));
        foreach ($this->getDays() as $d) {
            if ($date === $d->getDate()) {
                return $d;
            }
        }
        return null;
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
        
        if ($this->rooms = $this->db->long_cache_get_array('Roombooking\Room')) {
            Config::debug("School::getRooms: Successfully found in database");
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
        
        $this->db->long_cache_put_array($this->rooms);
        
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
    
    /*
     * 
     * This is the semi-VOLATILE section- this stuff needs fetching every session
     * 
     */

    public function getTimetables() {
        foreach ($this->getQueryData()['TimetablePeriodGrouping'] as $p) {
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
        
        foreach ($this->getQueryData()['CalendarEntryMapping'] as $d) {
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
        foreach ($this->getQueryData()['RoomUnavailability'] as $u) {
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
     * @return \Roombooking\Staff
     */
    public function getCurrentlyLoggedInStaff() {
        /* Depends on $auth_user */
        
        if (isset ($_SESSION['currentlyLoggedInStaffId'])) {
            return $this->staff[$_SESSION['currentlyLoggedInStaffId']];
        }
        
        /* TODO Remove this, it should be in auth.php or similar */
        //$auth_user = preg_replace('/@' . Config::site_emaildomain . '/', "", $_SERVER['PHP_AUTH_USER']);
        //$auth_user = 'abbie.young';
        $auth_user = 'henry.allen';
        
        Config::debug("School::getCurrentlyLoggedInStaff: looking for email");
        $emailAddress = $auth_user . "@" . Config::site_emaildomain;
        
        $emailQuery = "{ EmailAddress (emailAddress: \"$emailAddress\") { emailAddressOwner { ... on Staff { id entityType displayName } }}}";
        $emailAddress = $this->client->rawQuery($emailQuery)->getData()['EmailAddress'];
        Config::debug("School::getCurrentlyLoggedInStaff: query complete");
        if (!isset($emailAddress[0])) {
            die("Your email address " . $auth_user . '@' . Config::$site_emaildomain ." appears unrecognised.");
        }
        if (isset($emailAddress[1])) {
            die("Your email address appears to have more than one owner.  This cannot possibly be right");
        }
        if ($emailAddress[0]['emailAddressOwner']['entityType'] != 'Staff') {
            die("Your email address " . $auth_user . '@' . Config::$site_emaildomain ." appears not to belong to a member of staff.");
        }
        Config::debug("School::getCurrentlyLoggedInStaff: email found");
        $s = $emailAddress[0]['emailAddressOwner'];
        
        /* May as well, save a few microseconds if we need it later */
        $this->staff[$s['id']] = new Staff($s['id'], $s['displayName']);
        
        $_SESSION['currentlyLoggedInStaffId'] = $s['id'];
        
        return $this->staff[$s['id']];
    }
    
    /**
     * This is the Big Kahuna of queries, that should get everything we need in one fetch.  Needs splitting up!
     * 
     * Bonus is, we can serialise the Data from it, so it's easy to cache in session.
     *
     * @return array
     */
    public function getQueryData() {
        if (isset($_SESSION['School_queryData'])) {
            return $_SESSION['School_queryData'];
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
        $_SESSION['School_queryData'] = $this->client->rawQuery('
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
        return $_SESSION['School_queryData'];
    }
    
    public function resetQuery() {
        unset ($_SESSION['School_queryData']);
    }
    
    function __destruct()
    {}
}

