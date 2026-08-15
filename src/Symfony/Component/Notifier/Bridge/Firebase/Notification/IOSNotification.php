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

final class IOSNotification extends FirebaseOptions
{
    public function __construct(string $target, array $options = [], array $data = [], TargetType $targetType = TargetType::Topic)
    {
        trigger_deprecation('symfony/firebase-notifier', '8.2', 'Using %s class is deprecated, use %s instead.', self::class, FirebaseOptions::class);

        parent::__construct($target, ['notification' => $options], $data, $targetType);
    }

    /**
     * @return $this
     */
    public function sound(string $sound): static
    {
        return $this->addApnsOption('sound', $sound);
    }

    /**
     * @return $this
     */
    public function badge(string $badge): static
    {
        return $this->addApnsOption('badge', $badge);
    }

    /**
     * @return $this
     */
    public function clickAction(string $clickAction): static
    {
        return $this->addApnsOption('category', $clickAction);
    }

    /**
     * @return $this
     */
    public function subtitle(string $subtitle): static
    {
        return $this->addApnsAlertOption('subtitle', $subtitle);
    }

    /**
     * @return $this
     */
    public function bodyLocKey(string $bodyLocKey): static
    {
        return $this->addApnsAlertOption('body_loc_key', $bodyLocKey);
    }

    /**
     * @param string[] $bodyLocArgs
     *
     * @return $this
     */
    public function bodyLocArgs(array $bodyLocArgs): static
    {
        return $this->addApnsAlertOption('body_loc_args', $bodyLocArgs);
    }

    /**
     * @return $this
     */
    public function titleLocKey(string $titleLocKey): static
    {
        return $this->addApnsAlertOption('title_loc_key', $titleLocKey);
    }

    /**
     * @param string[] $titleLocArgs
     *
     * @return $this
     */
    public function titleLocArgs(array $titleLocArgs): static
    {
        return $this->addApnsAlertOption('title_loc_args', $titleLocArgs);
    }
}
