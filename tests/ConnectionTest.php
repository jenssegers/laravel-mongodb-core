<?php

namespace Jenssegers\Mongodb\Tests;

use Jenssegers\Mongodb\Connection;

class ConnectionTest extends TestCase
{
    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        parent::setUp();

        $this->connection = $this->app->get('db')->connection('mongodb');
    }

    public function testConnectionInstance()
    {
        $this->assertInstanceOf(Connection::class, $this->connection);
    }
}
