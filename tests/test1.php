<?php

require_once __DIR__ . '/../src/RedLock.php';

$servers = [
    ['127.0.0.1', 6379, 0.01],
    ['127.0.0.1', 6389, 0.01],
    ['127.0.0.1', 6399, 0.01],
];

$redLock = new RedLock($servers);

while (true) {
    $lock = $redLock->lock('test', 10000);

    if ($lock) {
        print_r($lock);
    } else {
        print "Lock not acquired\n";
    }
}
