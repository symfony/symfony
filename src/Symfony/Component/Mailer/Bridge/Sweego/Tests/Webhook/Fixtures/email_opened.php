<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '69b90094-872f-4250-97d5-515ac7114b1b', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-mailer' => 'Sweego',
    'x-swg-uid' => '02-6e5dbe48-e6f4-4af3-8fb4-bf125e75776b',
    'x-client-id' => 'f8367456332593298d050cf4bc83e0ab',
    'x-client-ip' => 'XXX.XXX.XXX.XXX',
    'x-campaign-id' => 'fake_campaign',
    'x-campaign-type' => 'default',
    'x-originating-ip' => 'XXX.XXX.XXX.XXX',
    'x-transaction-id' => '69b90094-872f-4250-97d5-515ac7114b1b',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-09-02T08:45:05+00:00'));

return $wh;
