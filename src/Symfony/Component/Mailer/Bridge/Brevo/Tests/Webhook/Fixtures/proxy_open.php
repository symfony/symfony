<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '<201798300811.5787683@relay.domain.com>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setTags(['transactionalTag']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1604933654));

return $wh;
