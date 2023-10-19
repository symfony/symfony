<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DROPPED, 'LRzXl_NHStOGhQ4kofSm_A.filterdrecv-p3mdw1-756b745b58-kmzbl-18-5F5FC76C-9.0', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)[0]);
$wh->setRecipientEmail('hello@world.com');
$wh->setTags([]);
$wh->setMetadata([]);
$wh->setReason('Bounced Address');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1600112492));

return $wh;
