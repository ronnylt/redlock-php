<?php
namespace RedLock;

class RedLock
{
    private $retryDelay;
    private $retryCount;
    private $clockDriftFactor = 0.01;

    private $quorum;

    private $servers = array();
    private $instances = array();

    function __construct(array $servers, $retryDelay = 200, $retryCount = 3)
    {
        $this->servers = $servers;

        $this->retryDelay = $retryDelay;
        $this->retryCount = $retryCount;

        $this->quorum  = min(count($servers), (count($servers) / 2 + 1));

    }

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

    public function unlock($lock)
    {
        $this->initInstances();
        $resource = $lock['resource'];
        $token    = $lock['token'];

        $success = 0;
        $fail = 0;
        foreach ($this->instances as $instance) {
            if ($this->unlockInstance($instance, $resource, $token)) {
                $success += 1;
            } else {
                $fail += 1;
            }
        }
        return $fail == 0;
    }

    private function initInstances()
    {
        if (empty($this->instances)) {
            foreach ($this->servers as $server) {
                if ($server instanceof \Redis) {
                    if ($server->isConnected()) {
                        $redis = $server;
                    } else {
                        throw new \Exception("If you use \\Redis objects as argument, the \\Redis object must be connected.");
                    }
                } else {
                    list($host, $port, $timeout) = $server;
                    $redis = new \Redis();
                    $redis->connect($host, $port, $timeout);
                }
                $this->instances[] = $redis;
            }
        }
    }

    private function lockInstance($instance, $resource, $token, $ttl)
    {
        return $instance->set($resource, $token, ['NX', 'PX' => $ttl]);

    }

    private function unlockInstance($instance, $resource, $token)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("DEL", KEYS[1])
            else
                return 0
            end
        ';

        // If the redis object is using igbinary as serializer
        // we need to call serialize to make sure we the
        // value is serialized the same way in our above Lua
        // as when we called ->set()
        $serializedToken = $instance->_serialize($token);

        return $instance->eval($script, [$resource, $serializedToken], 1);
    }
}
