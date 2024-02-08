<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '00000000-0000-0000-0000-000000000000', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('john@example.com');
$wh->setTags(['welcome-email']);
$wh->setMetadata(['example' => 'value', 'example_2' => 'value']);
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sT', '2022-09-02T14:31:09Z'));

return $wh;
