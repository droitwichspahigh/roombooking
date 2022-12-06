<?php

namespace Roombooking;

use \GraphQL\Client;
use \GraphQL\Query;
use GraphQL;

class GraphQLClient {
    protected $client;
    
    function __construct() {
        $this->client = new Client(
            Config::arbor['site'] . 'graphql/query',
            ['Authorization' => 'Basic ' . base64_encode(Config::arbor['user'] . ':' . Config::arbor['password'])]);
    }
    
    function query(Query $query,  $vars = [['a' => 0]]) {
        try {
            return $this->client->runQuery($query, true, $vars);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            die("<pre>Really sorry, Arbor appears to be taking too long to respond.  Please try again, and if this repeats, try again later.</pre>");
        }
    }
    
    function rawQuery(string $query, $vars = [['a' => 0]]) {
        try {
            $db = new Database();
            $db->dosql("INSERT INTO `debug` (`message`) VALUES ('Query: $query');");
            $ret = $this->client->runRawQuery($query, true, $vars);
            $db->dosql("INSERT INTO `debug` (`message`) VALUES ('Query done');");
            return $ret;
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            die("<pre>Really sorry, Arbor appears to be taking too long to respond.  Please try again, and if this repeats, try again later.</pre>");
        }
    }
    
    function test() {
        /* Work around bug somewhere- dummy variable a = 0 */
        $results = $this->client->runRawQuery("{ Student (id: 5) { id } }", false, [['a' => 0]]);
        print_r($results->getData());
    }
    
}