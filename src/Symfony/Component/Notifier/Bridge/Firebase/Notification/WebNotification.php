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
use Symfony\Component\Notifier\Bridge\Firebase\TargetType;

final class WebNotification extends FirebaseOptions
{
    public function __construct(string $target, array $options = [], array $data = [], TargetType $targetType = TargetType::Topic)
    {
        trigger_deprecation('symfony/firebase-notifier', '8.2', 'Using %s class is deprecated, use %s instead.', self::class, FirebaseOptions::class);

        parent::__construct($target, ['notification' => $options], $data, $targetType);
    }

    /**
     * @return $this
     */
    public function icon(string $icon): static
    {
        return $this->addWebpushOption('icon', $icon);
    }

    /**
     * @return $this
     */
    public function clickAction(string $clickAction): static
    {
        return $this->addWebpushOption('click_action', $clickAction);
    }
}
