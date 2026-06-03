<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::UNSUBSCRIBE, '861aad97-e4e8-4aaf-9322-1b64835760b9', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-campaign-id' => '42',
    'x-campaign-tags' => 'billing',
    'x-campaign-type' => 'transac',
    'x-client-id' => 'd6b1222eb484fb8f4g8d4fg8cd4',
    'x-client-ip' => 'XXX.XXX.XXX.XXX',
    'x-mailer' => 'Sweeg',
    'x-originating-ip' => 'XXX.XXX.XXX.XXX',
    'x-ref-1' => '643524',
    'x-ref-2' => 'lervcn',
    'x-ref-3' => 'o10icr',
    'x-swg-uid' => '02-589edd0e-b7f2-4a1d-a3ea-a333cb9aabc0',
    'x-transaction-id' => '861aad97-e4e8-4aaf-9322-1b64835760b9',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-09-02T12:55:09+00:00'));

return $wh;
