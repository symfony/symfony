<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DROPPED, '5520621607', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('complainer@world.com');
$wh->setReason('Dropped due to complaining recipient');
$wh->setDate(DateTimeImmutable::createFromFormat('U', 1576710200));

return $wh;
