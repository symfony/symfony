<?php

use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;

$wh = new SmsEvent(SmsEvent::DELIVERED, '03-f237cd16-a013-4e35-a279-c9eaa994e82b', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: \JSON_THROW_ON_ERROR));
$wh->setRecipientPhone('0033612345678');

return $wh;
