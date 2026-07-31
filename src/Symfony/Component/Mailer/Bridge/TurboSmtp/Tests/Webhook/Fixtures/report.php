<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::SPAM, '5520611177', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('reader@world.com');
$wh->setDate(DateTimeImmutable::createFromFormat('U', 1576709780));

return $wh;
