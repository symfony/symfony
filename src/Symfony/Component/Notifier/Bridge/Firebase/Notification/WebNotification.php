<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Notification;

use Symfony\Component\Notifier\Bridge\Firebase\FirebaseOptions;

/**
 * @experimental in 5.1
 */
final class WebNotification extends FirebaseOptions
{
    public function icon(string $icon): self
    {
        $this->options['icon'] = $icon;

        return $this;
    }

    public function clickAction(string $clickAction): self
    {
        $this->options['click_action'] = $clickAction;

        return $this;
    }
}
