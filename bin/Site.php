<?php
namespace Roombooking;

class Site
{
    /**
     * @var array $rooms Contains all of the ICT rooms in the school
     */
    protected $rooms = [];
    protected $timetableQuery = [];
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
        $monday = date('Y-m-d', strtotime('last Monday', strtotime('tomorrow')));
        $saturday = date('Y-m-d', strtotime('next Saturday', strtotime('yesterday')));
        /* You don't want to know how long this query took to construct :( */
        $data = $this->client->rawQuery('
query {
    CalendarEntryMapping (calendar__id_in: [' . implode(",", $calendarIds) . '] endDatetime_after: "' . $monday . '" startDatetime_before: "' . $saturday . '") {
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
                calendarEntryTitle
                staff {
                    displayName
                }
            }
        }
    }
}')->getData();
        foreach ($data['CalendarEntryMapping'] as $d) {
            if (isset($d['event']['location'])) {
                if (isset($this->rooms[$d['event']['location']['id']])) {
                    $this->rooms[$d['event']['location']['id']]->addTTFrag($d['event']);
                }
            }
        }
        print_r($this->rooms);
        
        
    }

    function __destruct()
    {}
}

