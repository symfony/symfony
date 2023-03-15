<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '00000000-0000-0000-0000-000000000000', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('john@example.com');
$wh->setTags(['Test']);
$wh->setMetadata(['example' => 'value', 'example_2' => 'value']);
$wh->setReason('The server was unable to deliver your message (ex: unknown user, mailbox not found).');
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sT', '2022-09-02T14:29:19Z'));

return $wh;
