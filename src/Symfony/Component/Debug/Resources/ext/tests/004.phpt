--TEST--
Test symfony debug object life tracing
--SKIPIF--
<?php if (!extension_loaded("symfony_debug")) print "skip"; ?>
--FILE--
<?php 
namespace Psr\Log {

interface LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = array());

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = array());

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = array());

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = array());

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = array());

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function notice($message, array $context = array());

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = array());

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = array());

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, $message, array $context = array());
}
}

namespace {

class TestLog implements Psr\Log\LoggerInterface {
    public function emergency($message, array $context = array()) { }
    public function alert($message, array $context = array()) { }
    public function critical($message, array $context = array()) { }
    public function error($message, array $context = array()) { }
    public function warning($message, array $context = array()) { }
    public function notice($message, array $context = array()) { }
    public function info($message, array $context = array()) { }
    public function debug($message, array $context = array()) { printf("$message \n"); }
    public function log($level, $message, array $context = array()) { }
}

$log = new TestLog;
symfony_debug_object_tracer_set_logger($log); 

$a = new StdClass;

$b = clone $a;

unset($b);


}
?>
--EXPECTF--
Creating object#2 of class stdClass in %s:%d 
Cloning object#2 of class stdClass in %s:%d 
Destroying object#3 of class stdClass in %s:%d 
Destroying object#2 of class stdClass in [no active file]:0