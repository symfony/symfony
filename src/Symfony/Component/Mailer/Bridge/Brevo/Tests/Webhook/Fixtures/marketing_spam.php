<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$event = new MailerEngagementEvent(MailerEngagementEvent::SPAM, '1239108', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$event->setRecipientEmail('example@gmail.com');
$event->setDate(\DateTimeImmutable::createFromFormat('U', 1734082566));

return $event;
