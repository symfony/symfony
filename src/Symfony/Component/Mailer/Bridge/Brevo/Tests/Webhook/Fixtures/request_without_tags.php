<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, '<202305311313.92192897094@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685538788));

return $wh;
