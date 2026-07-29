<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$payload = json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true);

$wh1 = new MailerDeliveryEvent(MailerDeliveryEvent::DELIVERED, '00000000-0000-0000-0000-000000000001', $payload[0]['data']);
$wh1->setRecipientEmail('receiver@example.com');
$wh1->setReason('Message delivered successfully');
$wh1->setDate(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2026-03-18T00:22:20.285574+00:00'));

$wh2 = new MailerEngagementEvent(MailerEngagementEvent::OPEN, '00000000-0000-0000-0000-000000000005', $payload[1]['data']);
$wh2->setRecipientEmail('receiver@example.com');
$wh2->setDate(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2026-03-18T10:34:52.130359+00:00'));

return [$wh1, $wh2];
