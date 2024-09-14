<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '00000000-0000-0000-0000-000000000000', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)['events'][0]);
$wh->setRecipientEmail('receiver@example.com');
$wh->setTags(['Password reset']);
$wh->setMetadata(['variable_a' => 'value', 'variable_b' => 'value2']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1726358034));

return [$wh];
