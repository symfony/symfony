<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DROPPED, '<202305311400.29297141656@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setTags(['tag1', 'tag2']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685541608));
$wh->setReason('blocked : due to unsubscribed user');

return $wh;
