<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DEFERRED, '<202305311400.29297141656@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@domain.com');
$wh->setTags(['transac_messages']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1604933654));
$wh->setReason('spam');

return $wh;
