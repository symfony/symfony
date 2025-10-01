<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '62fb66bef54a112e920b5493', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('test@example.com');
$wh->setTags(["test-tag"]);
$wh->setMetadata([
    'ip' => '127.0.0.1',
]);
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2024-01-01T12:00:00.000000Z'));

return $wh;
