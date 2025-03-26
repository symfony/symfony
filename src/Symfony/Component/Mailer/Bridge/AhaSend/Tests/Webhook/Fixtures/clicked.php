<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, 'ahasend-message-id', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('someone@example.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uT', '2024-10-28T18:30:01.799449Z'));

return $wh;
