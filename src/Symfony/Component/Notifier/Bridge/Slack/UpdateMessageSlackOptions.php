<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack;

/**
 * @author Maxim Dovydenok <dovydenok.maxim@gmail.com>
 */
final class UpdateMessageSlackOptions extends SlackOptions
{
    public function __construct(string $channelId, string $messageId, array $options = [])
    {
        $options['channel'] = $channelId;
        $options['ts'] = $messageId;

        parent::__construct($options);
    }
}
