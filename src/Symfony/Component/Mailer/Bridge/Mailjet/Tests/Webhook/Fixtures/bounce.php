<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '104427216766056450', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('event-bounce@yahoo.fr');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685525050));
$wh->setReason('policy issue');
$wh->setTags(['helloworld']);
$wh->setMetadata(['Payload' => '']);

return $wh;
