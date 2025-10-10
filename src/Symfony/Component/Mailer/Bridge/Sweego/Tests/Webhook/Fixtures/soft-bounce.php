<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, 'f11f4fe2-fb05-4882-8303-a20956d47931', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('recipient@example.com');
$wh->setMetadata([
    'x-campaign-type' => 'default',
    'x-swg-uid' => '01-4f5e28484-b515-4848-1956-2e3d0d176ef1',
    'x-mailer' => 'Sweego',
    'x-client-id' => 'MypersonnalId',
    'x-originating-ip' => 'XXX.XXX.XXX.XXX',
    'x-campaign-ref' => '895000N',
    'x-campaign-pool' => 'default',
    'x-campaign-id' => 'b20dsfsdfds6-9595b5-9595a7-565b9-5e2b826595053',
    'x-campaign-canal' => 'mycanal',
    'x-transaction-id' => 'f11f4fe2-fb05-4882-8303-a20956d47931',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat(\DATE_ATOM, '2024-08-20T08:38:27+00:00'));

return $wh;
