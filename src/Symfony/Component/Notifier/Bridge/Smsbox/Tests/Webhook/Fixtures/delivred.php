<?php

use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;

parse_str(trim(file_get_contents(str_replace('.php', '.txt', __FILE__))), $payload);
$wh = new SmsEvent(SmsEvent::DELIVERED, '250207960297', $payload);
$wh->setRecipientPhone('33612346578');

return $wh;
