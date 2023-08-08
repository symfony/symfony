<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, 'sg_event_id', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: \JSON_THROW_ON_ERROR)[0]);
$wh->setRecipientEmail('example@test.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1513299569));
$wh->setReason('500 unknown recipient');
$wh->setTags(['cat facts']);

return $wh;
