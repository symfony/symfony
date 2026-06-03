<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, '92042317804662640', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('event-sent@gmail.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685518742));
$wh->setReason('250 2.0.0 OK  1685518742 k22-20020a05600c0b5600b003f6020d9976si8376621wmr.181 - gsmtp');
$wh->setTags(['helloworld']);
$wh->setMetadata(['Payload' => '']);

return $wh;
