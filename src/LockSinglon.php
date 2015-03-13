<?php
/**
 * LockSinglon.php
 * Author: hyg huangyg11@gmail.com
 * Created at: 15-3-13 18:39
 */

class LockSinglon {
    private static $instance = null;

    /**
     * @return mixed
     */
    private static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new RedLock(REDIS_LOCK_SERVERS, 200, 1);
        }
        return self::$instance;
    }

    /**
     * @param $name
     * @param $param
     * @return mixed
     */
    public static function __callstatic( $name, $param ){
        return call_user_func_array( array( self::getInstance(), $name ), $param );
    }
}
