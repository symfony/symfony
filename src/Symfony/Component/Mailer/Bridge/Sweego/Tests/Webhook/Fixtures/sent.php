<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, 'd4fbec9d-eed9-44d5-af47-c1126467a5ca', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-transaction-id' => 'd4fbec9d-eed9-44d5-af47-c1126467a5ca',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-08-15T16:05:59+00:00'));

return $wh;
