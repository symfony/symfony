<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh1 = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '00000000-0000-0000-0000-000000000001', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)['events'][0]);
$wh1->setRecipientEmail('receiver@example.com');
$wh1->setTags(['Password reset']);
$wh1->setMetadata(['variable_a' => 'value', 'variable_b' => 'value2']);
$wh1->setReason('[CS01] Message rejected due to local policy');
$wh1->setDate(\DateTimeImmutable::createFromFormat('U', 1726358034));

$wh2 = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '00000000-0000-0000-0000-000000000002', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)['events'][1]);
$wh2->setRecipientEmail('receiver@example.com');
$wh2->setTags(['Password reset']);
$wh2->setMetadata(['variable_a' => 'value', 'variable_b' => 'value2']);
$wh2->setDate(\DateTimeImmutable::createFromFormat('U', 1726358034));

return [$wh1, $wh2];
