<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '5520611178', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('bad@world.com');
$wh->setReason('550 mailbox unavailable');
$wh->setDate(DateTimeImmutable::createFromFormat('U', 1576709778));

return $wh;
