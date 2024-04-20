<?php

use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;

$wh = new SmsEvent(SmsEvent::DELIVERED, 'aaaaaaaa-bbbb-cccc-dddd-0123456789ab', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: \JSON_THROW_ON_ERROR));
$wh->setRecipientPhone('447700900000');

return $wh;
