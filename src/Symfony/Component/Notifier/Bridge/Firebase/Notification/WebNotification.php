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

final class WebNotification extends FirebaseOptions
{
    /**
     * @return $this
     */
    public function icon(string $icon): static
    {
        $this->options['icon'] = $icon;

        return $this;
    }

    /**
     * @return $this
     */
    public function clickAction(string $clickAction): static
    {
        $this->options['click_action'] = $clickAction;

        return $this;
    }
}
