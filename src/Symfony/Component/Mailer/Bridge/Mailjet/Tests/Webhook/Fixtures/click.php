<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '93449692684977140', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('event-click@hotmail.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685519224));
$wh->setTags(['helloworld']);
$wh->setMetadata(['Payload' => '']);

return $wh;
