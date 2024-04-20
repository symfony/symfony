<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '<202305311447.66548003588@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setTags(['welcome', 'tag2']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685544532));

return $wh;
