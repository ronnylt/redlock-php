<?php

namespace RedLock;

/**
 * Class RedLock
 *
 * @package RedLock
 * @author  Ronny Lopez <ronny@tangotree.io>
 */
class RedLock
{
    /**
     * @var int Seconds to delay retrying
     */
    private $retryDelay;

    /**
     * @var int Number lock attempt retries before giving up
     */
    private $retryCount;

    /**
     * @var float Account for Redis expires precision
     */
    private $clockDriftFactor = 0.01;

    /**
     * @var mixed
     */
    private $quorum;

    /**
     * @var array[] Array of server information arrays: [host, port, timeout]
     * @see \Redis::connect()
     */
    private $servers = array();

    /**
     * @var array|\Redis[]
     */
    private $instances = array();

    /**
     * RedLock constructor.
     *
     * @param array[] $servers    Each element should an array of host, port, timeout
     * @param int     $retryDelay Seconds delay between retries
     * @param int     $retryCount Number of times to retry
     *
     * @see \Redis::connect()
     */
    function __construct(array $servers, $retryDelay = 200, $retryCount = 3)
    {
        $this->servers = $servers;

        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;

        $this->quorum  = min(count($servers), (count($servers) / 2 + 1));
    }

    /**
     * @param string $resource Unique identifier
     * @param int    $ttl      Time to live
     *
     * @return array|bool
     */
    public function lock($resource, $ttl)
    {
        $this->initInstances();

        $token = uniqid();
        $retry = $this->retryCount;

        do {
            $n = 0;

            $startTime = microtime(true) * 1000;

            foreach ($this->instances as $instance) {
                if ($this->lockInstance($instance, $resource, $token, $ttl)) {
                    $n++;
                }
            }

            # Add 2 milliseconds to the drift to account for Redis expires
            # precision, which is 1 millisecond, plus 1 millisecond min drift
            # for small TTLs.
            $drift = ($ttl * $this->clockDriftFactor) + 2;

            $validityTime = $ttl - (microtime(true) * 1000 - $startTime) - $drift;

            if ($n >= $this->quorum && $validityTime > 0) {
                return [
                    'validity' => $validityTime,
                    'resource' => $resource,
                    'token'    => $token,
                ];

            } else {
                foreach ($this->instances as $instance) {
                    $this->unlockInstance($instance, $resource, $token);
                }
            }

            // Wait a random delay before to retry
            $delay = mt_rand(floor($this->retryDelay / 2), $this->retryDelay);
            usleep($delay * 1000);

            $retry--;

        } while ($retry > 0);

        return false;
    }

    /**
     * @param array $lock Array returned by RedLock::lock
     * @see lock()
     */
    public function unlock(array $lock)
    {
        $this->initInstances();
        $resource = $lock['resource'];
        $token    = $lock['token'];

        foreach ($this->instances as $instance) {
            $this->unlockInstance($instance, $resource, $token);
        }
    }

    /**
     * Initialise the Redis servers provided in the the constructor.
     */
    private function initInstances()
    {
        if (empty($this->instances)) {
            foreach ($this->servers as $server) {
                list($host, $port, $timeout) = $server;
                $redis = new \Redis();
                $redis->connect($host, $port, $timeout);

                $this->instances[] = $redis;
            }
        }
    }

    /**
     * @param \Redis $instance Redis instance to attempt to lock
     * @param string $resource Unique identifier
     * @param string $token    Unique token
     * @param int    $ttl      Time to live
     *
     * @return mixed
     */
    private function lockInstance($instance, $resource, $token, $ttl)
    {
        return $instance->set($resource, $token, ['NX', 'PX' => $ttl]);
    }

    /**
     * @param \Redis $instance Redis instance to unlock
     * @param string $resource Unique identifier
     * @param string $token    Unique token
     *
     * @return mixed
     */
    private function unlockInstance($instance, $resource, $token)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';
        return $instance->eval($script, [$resource, $token], 1);
    }
}
