<?php
namespace Roombooking;

class School
{
    /**
     * @var array $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $timetableQuery = [];
    protected $timetablePeriod = [];
    protected $isTermDay = [];
    protected $client;
    
    public function __construct()
    {
        /* First, we find out which rooms we need */
        $this->client = new GraphQLClient();
        
        $result = $this->client->rawQuery(
            '{ RoomRoomFeature (roomFeature__roomFeatureName: "'. Config::$roomFeatureName . '") { room { shortName roomName displayName } } }');
        
        foreach ($result->getData()['RoomRoomFeature'] as $r) {
            $this->rooms[$r['room']['id']] = new Room($r['room']['roomName']);
        }
        
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
        $calendarIds = [];
        echo '<pre>';
        /* Let's find out which Calendars we need to query */
        foreach (array_keys($this->rooms) as $rId) {
            $query = new \Arbor\Query\Query(\Arbor\Resource\ResourceType::CALENDAR);
            $query->addPropertyFilter(\Arbor\Model\Calendar::OWNER, \Arbor\Query\Query::OPERATOR_EQUALS, "/rest-v2/rooms/" . $rId);
            $query->addPropertyFilter(\Arbor\Model\Calendar::CALENDAR_TYPE . '.' . \Arbor\Model\CalendarType::CODE,
                \Arbor\Query\Query::OPERATOR_EQUALS,
                'ACADEMIC');
            array_push($calendarIds, (\Arbor\Model\Calendar::query($query))[0]->getResourceId());
        }
        
        /* OK, we'll for now just query this week */     
        $sunday = date('Y-m-d', strtotime('last Sunday', strtotime('tomorrow')));
        $saturday = date('Y-m-d', strtotime('next Saturday', strtotime('yesterday')));
        /* You don't want to know how long this query took to construct :( */
        $data = $this->client->rawQuery('
query {
    CalendarEntryMapping (calendar__id_in: [' . implode(",", $calendarIds) . '] startDatetime_after: "' . $sunday . '" startDatetime_before: "' . $saturday . '") {
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
    AcademicCalendarDate (startDate_after: "' . $sunday . '" startDate_before: "' . $saturday . '") {
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
        
        foreach ($data['AcademicCalendarDate'] as $d) {
            $this->isTermDay[date("Y-m-d", strtotime($d['startDate']))] = $d['isGoodSchoolDay'];
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
                
                $this->rooms[$d['event']['location']['id']]->addLesson(
                    $this->timetablePeriod[$startTime],
                    date("Y-m-d", strtotime($d['event']['startDatetime'])),
                    $d['event']['displayName'],
                    $staff
                );
            }
        }
        
        print_r($this->rooms);
    }
    
    /**
     * Returns an array of 'start time' => 'period name'
     * @return array
     */
    function getPeriods() {
        return $this->timetablePeriod;
    }

    function __destruct()
    {}
}

