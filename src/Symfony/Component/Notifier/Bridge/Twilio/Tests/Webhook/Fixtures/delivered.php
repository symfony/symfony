<?php

use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;

parse_str(trim(file_get_contents(str_replace('.php', '.txt', __FILE__))), $payload);
$wh = new SmsEvent(SmsEvent::DELIVERED, 'SM4262411b90e5464b98a4f66a49c57a97', $payload);
$wh->setRecipientPhone('+15622089096');

return $wh;
