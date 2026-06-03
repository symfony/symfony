<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh1 = new MailerEngagementEvent(
    MailerEngagementEvent::CLICK, '7761630', json_decode(
                                                 file_get_contents(
                                                     str_replace('.php', '.json', __FILE__)
                                                 ), true, flags: JSON_THROW_ON_ERROR
                                             )['mandrill_events'][0]
);
$wh1->setRecipientEmail('foo@example.com');
$wh1->setTags(['my_tag_1', 'my_tag_2']);
$wh1->setMetadata(['mandrill-var-1' => 'foo', 'mandrill-var-2' => 'bar']);
$wh1->setDate(\DateTimeImmutable::createFromFormat('U', 1365109999));

$wh2 = new MailerDeliveryEvent(
    MailerDeliveryEvent::DEFERRED, '7761631', json_decode(
                                                  file_get_contents(
                                                      str_replace('.php', '.json', __FILE__)
                                                  ), true, flags: JSON_THROW_ON_ERROR
                                              )['mandrill_events'][1]
);
$wh2->setRecipientEmail('foo@example.com');
$wh2->setTags(['my_tag_1', 'my_tag_2']);
$wh2->setMetadata(['mandrill-var-1' => 'foo', 'mandrill-var-2' => 'bar']);
$wh2->setDate(\DateTimeImmutable::createFromFormat('U', 1365109999));
$wh2->setReason('');

return [$wh1, $wh2];
