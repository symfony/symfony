<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '102175416994919440', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('event-open@gmail.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685519055));
$wh->setTags(['helloworld']);
$wh->setMetadata(['Payload' => '']);

return $wh;
