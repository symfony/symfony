<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, '8a3bf3ee-1863-4a02-906d-2e6494914ddb', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-campaign-type' => 'default',
    'x-swg-uid' => '01-47d3ekdpj-1fdb-4bde-bsd5-bfbf32sdgf54f',
    'x-mailer' => 'Sweego',
    'x-campaign-id' => 'default',
    'x-client-id' => '0c8cc711c85e45b79189456644166sj',
    'x-originating-ip' => '185.255.28.207',
    'x-email-id' => '23',
    'x-transaction-id' => '8a3bf3ee-1863-4a02-906d-2e6494914ddb',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-09-02T08:45:05+00:00'));

return $wh;
