<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, 'ahasend-message-id', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('someone@example.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uT', '2024-10-27T19:35:58.267106Z'));

return $wh;
