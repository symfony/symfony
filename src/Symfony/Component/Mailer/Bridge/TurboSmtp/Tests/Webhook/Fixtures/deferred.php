<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DEFERRED, '5520611177', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('hello@world.com');
$wh->setReason('400 try again later');
$wh->setDate(DateTimeImmutable::createFromFormat('U', 1576709776));

return $wh;
