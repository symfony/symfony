<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DEFERRED, 'Fs7-5t81S2ispqxqDw2U4Q', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR)['event-data']);
$wh->setRecipientEmail('alice@example.com');
$wh->setTags(['my_tag_1', 'my_tag_2']);
$wh->setMetadata(['my_var_1' => 'Mailgun Variable #1', 'my-var-2' => 'awesome']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U.u', '1521472262.908181'));
$wh->setReason("4.2.2 The email account that you tried to reach is over quota. Please direct\n4.2.2 the recipient to\n4.2.2  https://support.example.com/mail/?p=422");

return $wh;
