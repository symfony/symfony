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

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, 'd4fbec9d-eed9-44d5-af47-c1126467a5ca', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setReason('5.1.1 Mailbox not found');
$wh->setDate(new DateTimeImmutable('2026-01-15T10:33:00Z'));
$wh->setRecipientEmail('recipient@example.com');

return $wh;
