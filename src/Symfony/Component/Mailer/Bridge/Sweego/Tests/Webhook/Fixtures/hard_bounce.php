<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '8dea05e7-9e8b-43d7-b000-3f7d15304162', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-swg-uid' => '01-68d20f85-253e-4986-b7f0-0e4229df4d61',
    'x-client-id' => 'myid',
    'x-campaign-pool' => 'default',
    'x-campaign-canal' => 'mycanal',
    'x-campaign-type' => 'default',
    'x-mailer' => 'Sweego',
    'x-originating-ip' => 'XXX.XXX.XXX.XXX',
    'x-campaign-ref' => '895000#N',
    'x-campaign-id' => 'b205d7b6-9eb5-4ba7-b3b9-5e2b8cade053',
    'x-transaction-id' => '8dea05e7-9e8b-43d7-b000-3f7d15304162',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-08-20T08:48:35+00:00'));

return $wh;
