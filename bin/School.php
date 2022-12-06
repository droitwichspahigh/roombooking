<?php
namespace Roombooking;

class School
{
    /**
     * @var array(\Roombooking\Room) $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $days = [];
    protected $timetableQuery = [];
    protected $timetablePeriod = [];
    protected $client, $db;
    protected $staff = null;
    
    public function __construct($date)
    {
        $this->client = new GraphQLClient();
        $this->db = new Database();
        $this->getQueryData($date);
        $this->getTimetables($date);
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
    function getRoomAcademicCalendarIds() {
        $cals = [];
        /* Let's find out which Calendars we need to query- these are the Rooms we should query */
        foreach ($this->getBookableRooms() as $r) {
            $cals[$r->getId()] = $r->getAcademicCalendarId();
        }
        
        return $cals;
    }
    
    /**
     *
     * @return int[] Array of calendar IDs.  The room calendars are stored under Room ID.
     */
    function getRoomSchoolCalendarIds() {
        $cals = [];
        /* Let's find out which Calendars we need to query- these are the Rooms we should query */
        foreach ($this->getBookableRooms() as $r) {
            $cals[$r->getId()] = $r->getSchoolCalendarId();
        }
        
        return $cals;
    }
    
    function getAY($element = 'start') {
        if (isset($this->academic_year[$element])) {
            return $this->academic_year[$element];
        }
        
        /* Let's find our academic year before we go any further */
        $month = date("m");
        $year = date("Y");
        
        if ($month >= 9) {
            $this->academic_year['eve'] = "$year-08-31";
            $this->academic_year['start'] = "$year-09-01";
            $this->academic_year['end'] = $year+1 . "-08-31";
            $this->academic_year['post'] = $year+1 . "-09-01";
        } else {
            $this->academic_year['eve'] = $year-1 . "-08-31";
            $this->academic_year['start'] = $year-1 . "-09-01";
            $this->academic_year['end'] = "$year-08-31";
            $this->academic_year['post'] = "$year-09-01";
        }
        
        /* Is it something we actually know? */
        if (!array_key_exists($element, $this->academic_year))
            die("There isn't such an element; $element for academic year");
            
            return $this->getAY($element);
    }
    
    /**
     * Give ten working days away- the cutoff for bookings.  This needs to be dynamic if caching.
     *
     * @return \Roombooking\Day
     */
    function getTenWorkingDaysFromNow() {
        $tenDays = 10;
        foreach ($this->getDays() as $d) {
            if (strtotime($d->getDate()) < time()) {
                continue;
            }
            if ($d->isTermDay()) {
                $tenDays--;
                if ($tenDays == 0) {
                    return $d;
                }
            }
        }
        // We must be less than ten days away from the end of term.
        return $d;
    }
    
    function getAcademicYear() {
        if (!empty($this->academicYear)) {
            return $this->academicYear;
        }
        
        $month = date("m");
        $year = date("Y");
        
        if ($month >= 9) {
            $nextYear = $year + 1;
            $this->academicYear['eve'] = "$year-08-31";
            $this->academicYear['post'] = "$nextYear-09-01";
            $this->academicYear['academicYear'] = "$year-$nextYear";
        } else {
            $lastYear = $year - 1;
            $this->academicYear['eve'] = "$lastYear-08-31";
            $this->academicYear['post'] = "$year-09-01";
            $this->academicYear['academicYear'] = "$lastYear-$year";
        }
        
        return $this->academicYear;
    }
    
    /**
     * 
     * @return \Roombooking\Day[]
     */
    function getDays() {
        if (!empty($this->days)) {
            return $this->days;
        }
        
        if ($this->days = $this->db->long_cache_get_array('Roombooking\Day')) {
            Config::debug("School::getDays: Successfully found in database");
            return $this->days;
        }
        
        $ayeve = $this->getAcademicYear()['eve'];
        $aypost = $this->getAcademicYear()['post'];
        
        $query = $this->client->rawQuery('{
            AcademicCalendarDate (startDate_after: "' . $ayeve . '" startDate_before: "' . $aypost . '") {
                startDate
                dayOfTerm
                isGoodSchoolDay
            }
        }')->getData();
        
        foreach ($query['AcademicCalendarDate'] as $d) {
            $day = new Day($d['id'], date("Y-m-d", strtotime($d['startDate'])), $d['isGoodSchoolDay']);
            $this->days[$day->getId()] = $day;
        }
        
        $this->db->long_cache_put_array($this->days);
        return $this->days;
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
                BookableRoom: RoomRoomFeature (roomFeature__roomFeatureName_in: ["'. implode('", "', Config::roomFeatureName) . '"]) {
                    room {
                        id
                        roomName
                        studentCapacity
                    }
                    roomFeature {
                        id
                        displayName
                    }
                }
                AllRoom: Room {
                    id
                    roomName
                    studentCapacity
                }
            }');
        
        foreach ($result->getData()['BookableRoom'] as $r) {
            $this->rooms[$r['room']['id']] = new Room($r['room']['id'], $r['room']['roomName'], true, $r['roomFeature']['displayName']);
            $this->rooms[$r['room']['id']]->setCapacity($r['room']['studentCapacity']);
        }
        
        foreach ($result->getData()['AllRoom'] as $r) {
            $bookable = false;
            foreach (Config::specialRooms as $rId => $_) {
                if ($rId == $r['id']) {
                    $bookable = true;
                }
            }
            if ($bookable) {
                $this->rooms[$r['id']] = new Room($r['id'], $r['roomName'], true, 'special');
                $this->rooms[$r['id']]->setCapacity($r['studentCapacity']);
            } else {
                if (!array_key_exists($r['id'], $this->rooms)) {
                    // If we have allRooms defined, get info for every room, not just bookable rooms
                    $this->rooms[$r['id']] = new Room($r['id'], $r['roomName'], defined('allRooms'));
                }
            }
        }
        
        /* It is most natural to sort Rooms by name and not ID */
        asort($this->rooms);
        
        $this->db->long_cache_put_array($this->rooms);
        
        // Try refreshing to avoid timeout
        header("Location: {$_SERVER['REQUEST_URI']}");
        die();
        
        return $this->rooms;
    }
    
    /**
     * @param string $feature
     *
     * @return \Roombooking\Room[]
     */
    public function getBookableRooms(string $feature = "") {
        $bookableRooms = [];
        foreach ($this->getRooms() as $id => $r) {
            if ($r->isBookable() && (empty($feature) || $r->getFeature() == $feature || ($feature == "" && $r->getFeature() == 'special'))) {
                $bookableRooms[$id] = $r;
            }
        }
        return $bookableRooms;
    }
    
    /**
     * Returns an array of Periods
     * @return array(\Roombooking\Period)
     */
    public function getPeriods() {
        if (!empty($this->timetablePeriod)) {
            return $this->timetablePeriod;
        }
        
        if ($this->timetablePeriod = $this->db->long_cache_get_array('Roombooking\Period')) {
            Config::debug("School::getTimetablePeriods: Successfully found in database");
            return $this->timetablePeriod;
        }
        
        $ay = $this->getAcademicYear()['academicYear'];
        
        $queryData = $this->client->rawQuery('
        {
            TimetablePeriodGrouping (academicYear__code: "' . $ay . '") {
                shortName
                timetablePeriods {
                    dayOfWeek
                    dayOfCycle
                    startTime
                    endTime
                }
            }
        }')->getData();
        
        foreach ($queryData['TimetablePeriodGrouping'] as $p) {
            /*
             * XXX This assumes that Periods are the same regardless of day,
             *     so places with special Wednesdays or Fridays for example
             *     aren't going to manage with this!
             *
             *     Hint: you'll know if this is the case when you connect it
             *     to Arbor and the room booking suite crashes...
             */
            $this->timetablePeriod[$p['timetablePeriods'][0]['startTime']] =
            new Period($p['id'], $p['shortName'], $p['timetablePeriods'][0]['startTime'], $p['timetablePeriods'][0]['endTime']);
        }
        
        /* Sort timetablePeriods by starting time, not when they were entered! */
        ksort($this->timetablePeriod);
        
        $this->db->long_cache_put_array($this->timetablePeriod);
        
        // Avoid timeout
        header("Location: {$_SERVER['REQUEST_URI']}");
        die();
        
        return $this->timetablePeriod;
    }
    
    public function getPeriodFromTimes(String $startTime, String $endTime) {
        foreach ($this->getPeriods() as $p) {
            if ($p->getStartTime() == $startTime || $p->getEndTime() == $endTime) {
                return $p;
            }
        }
        return null;
    }
    
    /*
     * 
     * This is the semi-VOLATILE section- this stuff needs fetching every session
     * 
     */

    public function getTimetables(String $date) {
        foreach ($this->getQueryData($date)['CalendarEntryMapping'] as $d) {
            // First deal with lessons
            if (isset($d['lesson']['location'])) {
                if (!$this->getRooms()[$d['lesson']['location']['id']]->isBookable()) {
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
                $endTime = date("H:i:s",   strtotime($d['lesson']['endDatetime']));
                /* Based on startTime, not ID */
                $period = $this->getPeriodFromTimes($startTime, $endTime);
                if (! $period) {
                    continue;
                    die ("Hm, no timetable map for $startTime - $endTime. ttMap = " . print_r($this->getPeriods(), true) . "</pre> <p>Try <a href=\"clear_cache.php\">clearing the cache to see if that helps</a> before <a href=\"mailto:" . Config::support_email . "\">emailing</a>.</p>");
                }
                
                $day = $this->getDay($d['lesson']['startDatetime']);
                
                if ($day === null) {
                    die ("Hm, no Day for " . $d['lesson']['startDatetime'] . " Try <a href=\"clear_cache.php\">clearing the cache to see if that helps</a> before <a href=\"mailto:" . Config::support_email . "\">emailing</a>.");
                }
                
                $this->getRooms()[$d['lesson']['location']['id']]->addLesson(
                    new Lesson($d['lesson']['id'], $d['lesson']['displayName'], $day, $period, $staff)
                    );
            }
        }
        foreach ($this->getQueryData($date)['RoomUnavailability'] as $u) {
            // Then deal with availability
            $this->getRooms()[$u['room']['id']]->addUnavailability(
                new Unavailability(
                    $u['id'],
                    $u['displayName'],
                    strtotime($u['startDatetime']),
                    strtotime($u['endDatetime'])
                    )
                );
        }
        foreach ($this->getQueryData($date)['interventionsAndSundry'] as $i) {
            $roomCalendarId = $i['calendar']['id'];
            $roomId = array_search($roomCalendarId, $this->getRoomSchoolCalendarIds());
            if (!$this->getRooms()[$roomId]->isBookable()) {
                // This must be a non-ICT room, so a calendared lesson
                continue;
            }
            $this->rooms[$roomId]->addUnavailability(
                new Unavailability(
                    -$i['id'],
                    'Intervention or exam',
                    strtotime($i['startDatetime']),
                    strtotime($i['endDatetime'])
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
        global $auth_user;
        
        if (isset ($_SESSION['currentStaff'])) {
            return $_SESSION['currentStaff'];
        }
        
        Config::debug("School::getCurrentlyLoggedInStaff: looking for email");
        $emailAddress = $auth_user . "@" . Config::site_emaildomain;
        
        $emailQuery = "{ EmailAddress (emailAddress: \"$emailAddress\") { emailAddressOwner { ... on Staff { id entityType displayName } }}}";
        $emailAddress = $this->client->rawQuery($emailQuery)->getData()['EmailAddress'];
        Config::debug("School::getCurrentlyLoggedInStaff: query complete");
        if (!isset($emailAddress[0])) {
            die("Your email address " . $auth_user . '@' . Config::site_emaildomain ." appears unrecognised.");
        }
        
        $staffFound = false;
        foreach ($emailAddress as $e) {
            if ($e['emailAddressOwner']['entityType'] == "Staff") {
                if ($staffFound) {
                    die("Your email address appears to have more than one owner.  This cannot possibly be right");
                }
                $staffFound = true;
                $s = $e['emailAddressOwner'];
            }
        }
        
        if (!isset($s)) {
            die("Your email address " . $auth_user . '@' . Config::site_emaildomain ." appears not to belong to a member of staff.");
        }
        
        Config::debug("School::getCurrentlyLoggedInStaff: email found");

        /* May as well, save a few microseconds if we need it later */
        $this->staff[$s['id']] = new Staff($s['id'], $s['displayName']);
        
        $_SESSION['currentStaff'] = $this->staff[$s['id']];

        return $_SESSION['currentStaff'];
    }
    
    /**
     * This is the Big Kahuna of queries, that should get everything we need in one fetch.  Needs splitting up!
     * 
     * Bonus is, we can serialise the Data from it, so it's easy to cache in session.
     *
     * @return array
     */
    public function getQueryData($start) {
        if (isset($_SESSION['School_queryData'][$start])) {
            return $_SESSION['School_queryData'][$start];
        }
        
        Config::debug("School::getQueryData: no session cache data, requerying");
        
        $end = date('Y-m-d', strtotime('+1 day', strtotime($start)));
        //$lastMidnight = "2021-09-01";
        //$end = "2022-08-31";
        
        $page = 0;
        $_SESSION['School_queryData'] ??= [];
        $_SESSION['School_queryData'][$start] = [];
        $nextPageNeeded = false;
        $page_size = 1000;
        while ($page == 0 || $nextPageNeeded /* || !empty($queryData['CalendarEntryMapping']) || !empty($queryData['interventionsAndSundry']) || !empty($queryData['RoomUnavailability']) */) {
            /* You don't want to know how long this query took to construct :( */
            $query = '
    query {
        CalendarEntryMapping (calendar__id_in: [' . implode(",", $this->getRoomAcademicCalendarIds()) . "] startDatetime_after: \"$start\" startDatetime_before: \"$end\" page_num: $page page_size: $page_size) {
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
        interventionsAndSundry: CalendarEntryMapping (calendar__id_in: [" . implode(',', $this->getRoomSchoolCalendarIds()) . "] startDatetime_after: \"$start\" startDatetime_before: \"$end\" page_num: $page page_size: $page_size) {
            id        
            startDatetime
            endDatetime
            displayName
            calendar {
                id
            }" .
/*            event {
                __typename
            }
*/        "}
        RoomUnavailability (room__id_in: [" . implode (',', array_keys($this->getBookableRooms())). "] page_num: $page page_size: $page_size){
            room {
                id
            }
            startDatetime
            endDatetime
            displayName
        }
    }";
            //die($query);
            $queryData = $this->client->rawQuery($query)->getData();
            $nextPageNeeded = false;
            //echo "<pre>"; die(print_r($queryData, true));
            foreach (['RoomUnavailability', 'interventionsAndSundry', 'CalendarEntryMapping'] as $g) {
                if (count($queryData[$g]) >= $page_size-1) {
                    $nextPageNeeded = true;
                }
                $_SESSION['School_queryData'][$start][$g] = array_merge($_SESSION['School_queryData'][$start][$g] ?? [], $queryData[$g]);
            }
            $page += 1;
        }
        return $_SESSION['School_queryData'][$start];
    }
    
    public function resetQuery() {
        unset ($_SESSION['School_queryData']);
    }
    
    function __destruct()
    {}
}

