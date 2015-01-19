<?php

use \RedLock\RedLock;


class RedLockTest extends PHPUnit_Framework_TestCase
{
    private $servers;

    public function setUp()
    {
        $this->servers = array(
            array("127.0.0.1", 6379, 0.5),
            array("127.0.0.1", 6389, 0.5),
            array("127.0.0.1", 6399, 0.5),
        );
    }

    public function testLock ()
    {
        $redlock = new RedLock($this->servers);
        
        $resource = "my_test_resource".time();

        $lockA = $redlock->lock($resource, 500);
        $this->assertInternalType("array", $lockA);
        $this->assertArrayHasKey("validity", $lockA);
        $this->assertArrayHasKey("token", $lockA);
        $this->assertArrayHasKey("resource", $lockA);
        $this->assertEquals($resource, $lockA["resource"]);

        // sleep a little (but way less then the lock timeout)
        usleep(200);
        // try to lock again and expect it to fail
        $lockB = $redlock->lock($resource, 500);
        $this->assertFalse($lockB);

        // sleep a little (but again, way less then the lock timeout,
        // but totalling above a single lock timeout)
        usleep(310);

        // try to lock again and expect it to work
        $lockB = $redlock->lock($resource, 500);
        $this->assertInternalType("array", $lockB);
        $this->assertArrayHasKey("validity", $lockB);
        $this->assertArrayHasKey("token", $lockB);
        $this->assertArrayHasKey("resource", $lockB);
        $this->assertEquals($resource, $lockB["resource"]);

        // release lock B
        $this->assertTrue($redlock->unlock($lockB));

        // try to lock same resource as we just released
        // this should obviously work 
        $lockC = $redlock->lock($resource, 500);
        $this->assertInternalType("array", $lockC);
        $this->assertArrayHasKey("validity", $lockC);
        $this->assertArrayHasKey("token", $lockC);
        $this->assertArrayHasKey("resource", $lockC);
        $this->assertEquals($resource, $lockC["resource"]);

        // and just to be sure, try to quite it again emidiatly,
        // and expect it to fail
        $lockC = $redlock->lock($resource, 500);
        $this->assertFalse($lockC);
        $this->assertFalse($redlock->unlock($lockC));
    }

    public function testRedisObjectConstruct ()
    {
        $resource = "my_test_resource".time();

        $redisObjects = [];
        foreach ($this->servers as $server) {
            list($host, $port, $timeout) = $server;
            $redis = new \Redis();
            if (!$redis->connect($host, $port, $timeout)) {
                throw new \Exception("Redis didn't connect");
            }
            $redisObjects[] = $redis;
        }

        $redlock = new RedLock($redisObjects);

        $lockA = $redlock->lock($resource, 500);
        $this->assertInternalType("array", $lockA);
        $this->assertArrayHasKey("validity", $lockA);
        $this->assertArrayHasKey("token", $lockA);
        $this->assertArrayHasKey("resource", $lockA);
        $this->assertEquals($resource, $lockA["resource"]);
        $this->assertTrue($redlock->unlock($lockA));
    }

    public function testLockMultiple ()
    {
        $redlock = new RedLock($this->servers);
        $resource = "my_test_resource".time();

        $lockA = $redlock->lock($resource, 1000);
        $lockB = $redlock->lock($resource, 1000);
        $lockC = $redlock->lock($resource, 1000);

        $this->assertInternalType("array", $lockA);
        $this->assertFalse($lockB);
        $this->assertFalse($lockC);

        $this->assertTrue($redlock->unlock($lockA));

        $lockA = $redlock->lock($resource, 1000);
        $lockB = $redlock->lock($resource, 1000);

        $this->assertInternalType("array", $lockA);
        $this->assertFalse($lockB);

        $this->assertTrue($redlock->unlock($lockA));


    }
}
