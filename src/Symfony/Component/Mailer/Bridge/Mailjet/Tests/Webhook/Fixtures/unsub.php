<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::UNSUBSCRIBE, '20547674933128000', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('api@mailjet.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1433334941));
$wh->setTags(['helloworld']);
$wh->setMetadata(['Payload' => '']);

return $wh;
