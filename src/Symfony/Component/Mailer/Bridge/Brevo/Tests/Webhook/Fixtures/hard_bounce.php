<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, '<202305311437.85220493321@smtp-relay.mailin.fr>', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR));
$wh->setRecipientEmail('example@gmail.com');
$wh->setTags(['welcome', 'tag2']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1685543827));
$wh->setReason("550-5.1.1 The email account that you tried to reach does not exist. Please try\n   550-5.1.1 double-checking the recipient's email address for typos or\n   550-5.1.1 unnecessary spaces. Learn more at\n   550 5.1.1  https://support.google.com/mail/?p=NoSuchUser g14-20020a5d698e000000b0030aefbb8608si2668200wru.496 - gsmtp");

return $wh;
