<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, '7761632', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR)['mandrill_events'][0]);
$wh->setRecipientEmail('foo@example.com');
$wh->setTags(['my_tag_1', 'my_tag_2']);
$wh->setMetadata(['mandrill-var-1' => 'foo', 'mandrill-var-2' => 'bar']);
$wh->setDate(\DateTimeImmutable::createFromFormat('U', 1365109999));

return [$wh];
