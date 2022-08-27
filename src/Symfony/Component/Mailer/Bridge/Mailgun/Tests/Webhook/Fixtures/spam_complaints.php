<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::SPAM, '-Agny091SquKnsrW2NEKUA', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR)['event-data']);
$wh->setRecipientEmail('alice@example.com');
$wh->setTags(['my_tag_1', 'my_tag_2']);
$wh->setMetadata(['my_var_1' => 'Mailgun Variable #1', 'my-var-2' => 'awesome']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U.u', 1521233123.501324));

return $wh;
