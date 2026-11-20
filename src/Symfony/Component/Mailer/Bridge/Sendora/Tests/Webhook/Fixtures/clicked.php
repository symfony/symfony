<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '5f432ffd-c005-499b-aa3a-5b8a088ea20e', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('anna@example.com');
$wh->setTags(['invoice']);
$wh->setMetadata(['invoiceId' => '2026-0912']);
$wh->setDate(new \DateTimeImmutable('2026-09-15T12:00:03.000Z'));

return $wh;
