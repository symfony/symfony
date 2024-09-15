<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh1 = new MailerDeliveryEvent(MailerDeliveryEvent::DROPPED, 'LRzXl_NHStOGhQ4kofSm_A.filterdrecv-p3mdw1-756b745b58-kmzbl-18-5F5FC76C-9.0', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)[0]);
$wh1->setRecipientEmail('hello@world.com');
$wh1->setTags([]);
$wh1->setMetadata([]);
$wh1->setReason('Bounced Address');
$wh1->setDate(\DateTimeImmutable::createFromFormat('U', 1600112492));

$wh2 = new MailerEngagementEvent(MailerEngagementEvent::CLICK, 'LRzXl_NHStOGhQ4kofSm_A.filterdrecv-p3mdw1-756b745b58-kmzbl-18-5F5FC76C-9.0', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)[1]);
$wh2->setRecipientEmail('hello@world.com');
$wh2->setTags([]);
$wh2->setMetadata([]);
$wh2->setDate(\DateTimeImmutable::createFromFormat('U', 1600112492));

return [$wh1, $wh2];
