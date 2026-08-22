<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '67f91abd69f79df391e9d78d', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('test@example.com');
$wh->setTags(["test-tag"]);
$wh->setMetadata([
    'ip' => '192.168.1.1',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2026-01-01T12:00:00.000000Z'));

return $wh;
