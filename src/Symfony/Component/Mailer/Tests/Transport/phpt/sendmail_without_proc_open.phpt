--TEST--
SendmailTransport throws a TransportException when proc_open() is disabled
--INI--
disable_functions=proc_open
--FILE--
<?php

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Email;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$email = (new Email())->from('from@example.com')->to('to@example.com')->text('Hello');

foreach (['/usr/sbin/sendmail -bs', '/usr/sbin/sendmail -t'] as $command) {
    try {
        (new SendmailTransport($command))->send($email);
    } catch (TransportException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
Unable to start the process: the "proc_open()" function is not available on your PHP installation.
Unable to start the process: the "proc_open()" function is not available on your PHP installation.
