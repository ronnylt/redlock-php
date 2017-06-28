<?php

/**
 * Class RedLockTestListener
 *
 * @author
 */
class RedLockTestListener implements PHPUnit_Framework_TestListener
{
    public function startTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        if('RedLock Test Suite' != $suite->getName())
        {
            return;
        }
        // Start the Redis servers
        passthru(sprintf('redis-server --port %d --daemonize yes', RedLockTest::SERVER_PORT_A));
        passthru(sprintf('redis-server --port %d --daemonize yes', RedLockTest::SERVER_PORT_B));
        passthru(sprintf('redis-server --port %d --daemonize yes', RedLockTest::SERVER_PORT_C));
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite)
    {
        if('RedLock Test Suite' != $suite->getName())
        {
            return;
        }
        // Stop the Redis servers
        passthru(sprintf('redis-cli -p %d shutdown', RedLockTest::SERVER_PORT_A));
        passthru(sprintf('redis-cli -p %d shutdown', RedLockTest::SERVER_PORT_B));
        passthru(sprintf('redis-cli -p %d shutdown', RedLockTest::SERVER_PORT_C));
    }

    public function startTest(PHPUnit_Framework_Test $test)
    {
    }

    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time)
    {
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addRiskyTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time)
    {
    }
}