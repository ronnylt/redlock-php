<?php

/**
 * Class RedLockTest
 *
 * @author Peter Scopes
 */
class RedLockTest extends PHPUnit_Framework_TestCase
{
    const SERVER_PORT_A = 6389;
    const SERVER_PORT_B = 6390;
    const SERVER_PORT_C = 6391;

    /**
     * @var array[]
     */
    protected $servers;

    public function setUp()
    {
        $this->servers = [
            ['127.0.0.1', self::SERVER_PORT_A, 0.1],
            ['127.0.0.1', self::SERVER_PORT_B, 0.1],
            ['127.0.0.1', self::SERVER_PORT_C, 0.1],
        ];
    }

    public function testLockOk()
    {
        $redLock  = new \RedLock\RedLock($this->servers);
        $resource = 'redLock.key';
        $gate     = $redLock->lock($resource, 500);
        $redLock->unlock($gate);

        // Asserts
        $this->assertInternalType('array', $gate);
        $this->assertArrayHasKey('validity', $gate);
        $this->assertArrayHasKey('token', $gate);
        $this->assertArrayHasKey('resource', $gate);
        $this->assertEquals($resource, $gate['resource']);

    }

    public function testBlockOk()
    {
        $redLock  = new \RedLock\RedLock($this->servers);
        $resource = 'redLock.key';

        $gateA    = $redLock->lock($resource, 500);
        $gateB    = $redLock->lock($resource, 500);
        $redLock->unlock($gateA);

        // Asserts
        $this->assertFalse($gateB);
    }

    public function testTimeoutOk()
    {
        $redLock  = new \RedLock\RedLock($this->servers);
        $resource = 'redLock.key';

        $gateA    = $redLock->lock($resource, 500);
        $gateB    = $redLock->lock($resource, 500);

        $this->assertInternalType('array', $gateA);
        $this->assertFalse($gateB);

        usleep(500000);

        $gateB    = $redLock->lock($resource, 500);
        $redLock->unlock($gateB);
        $this->assertInternalType('array', $gateB);
    }

    public function testMultipleOk()
    {
        $redLock  = new \RedLock\RedLock($this->servers);
        $resource = 'redLock.key';

        $gateA    = $redLock->lock($resource, 1000);
        $gateB    = $redLock->lock($resource, 1000);
        $gateC    = $redLock->lock($resource, 1000);
        $redLock->unlock($gateA);

        // Asserts
        $this->assertInternalType('array', $gateA);
        $this->assertFalse($gateB);
        $this->assertFalse($gateC);

        $gateB    = $redLock->lock($resource, 1000);
        $gateC    = $redLock->lock($resource, 1000);
        $gateA    = $redLock->lock($resource, 1000);
        $redLock->unlock($gateB);

        // Asserts
        $this->assertInternalType('array', $gateB);
        $this->assertFalse($gateC);
        $this->assertFalse($gateA);

        $gateC    = $redLock->lock($resource, 1000);
        $gateA    = $redLock->lock($resource, 1000);
        $gateB    = $redLock->lock($resource, 1000);
        $redLock->unlock($gateC);

        // Asserts
        $this->assertInternalType('array', $gateC);
        $this->assertFalse($gateA);
        $this->assertFalse($gateB);
    }
}