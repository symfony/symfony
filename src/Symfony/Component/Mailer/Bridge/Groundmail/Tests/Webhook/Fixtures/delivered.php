<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, 'msg_01JC8Q7K2Z', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('user@example.com');
$wh->setMetadata(['user_id' => 'u_42']);
$wh->setReason('');
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2026-10-17T15:02:11.000Z'));

return $wh;
