--TEST--
Test that rendered exceptions are logged to syslog with the error priority, like PHP does
--INI--
display_errors=0
log_errors=1
error_log=syslog
syslog.ident=symfony-test
syslog.facility=daemon
--FILE--
<?php

namespace Symfony\Component\ErrorHandler;

function openlog(string $prefix, int $flags, int $facility): bool
{
    echo 'openlog(', $prefix, ', ', $flags, ', ', \LOG_DAEMON === $facility ? 'LOG_DAEMON' : $facility, ")\n";

    return true;
}

function syslog(int $priority, string $message): bool
{
    echo 'syslog(', \LOG_ERR === $priority ? 'LOG_ERR' : $priority, '): ', $message, "\n";

    return true;
}

function error_log(string $message, int $messageType = 0): bool
{
    echo 'error_log(', $messageType, '): ', $message, "\n";

    return true;
}

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$_SERVER['NO_COLOR'] = '1';
ErrorHandler::register();

throw new \RuntimeException('foo');
?>
--EXPECTF--
openlog(symfony-test, 0, LOG_DAEMON)
syslog(LOG_ERR): PHP Fatal error:  Uncaught RuntimeException: foo in %s:%d
Stack trace:
#0 {main}
  thrown in %s on line %d
RuntimeException {%S
  #message: "foo"
  #code: 0
  #file: "%s"
  #line: %d
}
