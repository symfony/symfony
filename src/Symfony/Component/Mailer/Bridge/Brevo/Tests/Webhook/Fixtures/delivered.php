<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, '<202305311328.81899448177@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setTags(['tag1', 'tag2']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685539713));

return $wh;
