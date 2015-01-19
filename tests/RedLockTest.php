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
        
        $resource = "my_test_resource";

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
        $redlock->unlock($lockB);

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

        $redlock->unlock($lockC);

    }
}
