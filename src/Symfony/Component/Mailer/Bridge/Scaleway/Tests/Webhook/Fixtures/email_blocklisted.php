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

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::DROPPED, '3c5a7e19-6b2d-4f80-9c1e-5d7b3a9f1c4e', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setReason('The recipient mailbox does not exist');
$wh->setDate(new DateTimeImmutable('2026-01-15T10:34:00Z'));
$wh->setRecipientEmail('recipient@example.com');

return $wh;
