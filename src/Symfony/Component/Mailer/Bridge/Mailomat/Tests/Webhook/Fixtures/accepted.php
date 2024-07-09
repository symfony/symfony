<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, '29e785c1-dd0c-4efc-9d41-909d4109769f', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('to@mailomat.swiss');
$wh->setDate(DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, '2024-06-10T09:23:31+02:00'));

return $wh;
