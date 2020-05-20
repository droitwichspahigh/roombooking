<?php

namespace Roombooking;

use \GraphQL\Client;
use \GraphQL\Query;

class GraphQLClient {
    protected $client;
    
    function __construct() {
        $this->client = new Client(
            Config::arbor['site'] . 'graphql/query',
            ['Authorization' => 'Basic ' . base64_encode(Config::arbor['user'] . ':' . Config::arbor['password'])]);
    }
    
    function query(Query $query,  $vars = [['a' => 0]]) {
        return $this->client->runQuery($query, true, $vars);
    }
    
    function rawQuery(string $query, $vars = [['a' => 0]]) {
        return $this->client->runRawQuery($query, true, $vars);
    }
    
    function test() {
        /* Work around bug somewhere- dummy variable a = 0 */
        $results = $this->client->runRawQuery("{ Student (id: 5) { id } }", false, [['a' => 0]]);
        print_r($results->getData());
    }
    
}