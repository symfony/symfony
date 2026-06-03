<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, '4o7-z86dSkSI54c6IudeuA', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true, flags: JSON_THROW_ON_ERROR)['event-data']);
$wh->setRecipientEmail('alice@example.com');
$wh->setDate(\DateTimeImmutable::createFromFormat('U.u', '1707402341.91242'));

return $wh;
