<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, 'd4fbec9d-eed9-44d5-af47-c1126467a5ca', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-campaign-type' => 'default',
    'x-swg-uid' => '01-f14sqdf65-fgh9b6-4160-bc45-fliolioa277ca9',
    'x-mailer' => 'Sweego',
    'x-campaign-id' => 'default',
    'x-client-id' => '0c8cc711c85e4595953862',
    'x-originating-ip' => 'XXX.XXX.XXX.XX',
    'x-email-id' => '23',
    'x-transaction-id' => 'd4fbec9d-eed9-44d5-af47-c1126467a5ca',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-08-15T16:05:59+00:00'));

return $wh;
