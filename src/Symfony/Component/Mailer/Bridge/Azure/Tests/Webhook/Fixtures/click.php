<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;

$wh = new MailerEngagementEvent(MailerEngagementEvent::CLICK, '00000000-0000-0000-0000-000000000007', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true)[0]['data']);
$wh->setRecipientEmail('receiver@example.com');
$wh->setDate(DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2026-03-18T11:45:30.987654+00:00'));

return [$wh];
