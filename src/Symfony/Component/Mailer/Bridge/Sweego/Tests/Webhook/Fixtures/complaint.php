<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::SPAM, 'b0e50d6d-118c-459b-84e1-70209e68c1c9', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-mailer' => 'Sweego',
    'x-swg-uid' => '01-47d3ekdpj-1fdb-4bde-bsd5-bfbf32sdgf54f',
    'x-campaign-id' => 'default',
    'x-client-id' => '0c8cc711c85e45b79189456644166sj',
    'x-originating-ip' => '185.255.28.207',
    'x-campaign-type' => 'default',
    'x-transaction-id' => 'b0e50d6d-118c-459b-84e1-70209e68c1c9',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-09-02T08:45:05+00:00'));

return $wh;
