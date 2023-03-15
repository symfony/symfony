<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Event\Mailer;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
final class MailerEngagementEvent extends AbstractMailerEvent
{
    public const OPEN = 'open';
    public const CLICK = 'click';
    public const SPAM = 'spam';
    public const UNSUBSCRIBE = 'unsubscribe';
}
