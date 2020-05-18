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
    
    public function getTimetables() {
        $monday = date('Y-m-d', strtotime('last Monday', strtotime('tomorrow')));
        $friday = date('Y-m-d', strtotime('next Friday', strtotime('yesterday')));
        echo "Monday $monday Friday $friday <hr />";
        $page = 0;
        while ($data = ($this->client->rawQuery('
query {
    CalendarEntryMapping (page_num: ' . $page . ' endDatetime_after: "' . $monday . '" startDatetime_before: "' . $friday . '") {
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
}')->getData())) {
            if (!isset($data['CalendarEntryMapping'][0])) {
                /* No more pages! */                
                break;
            }
            foreach ($data['CalendarEntryMapping'] as $d) {
                if (isset($d['event']['location'])) {
                    if (isset($this->rooms[$d['event']['location']['id']])) {
                        $this->rooms[$d['event']['location']['id']]->addTTFrag($d['event']);
                    }
                }
            }
            $page++;
            if ($page > 5)
                break;
        }
        print_r($this->rooms);
        
        
    }

    function __destruct()
    {}
}

