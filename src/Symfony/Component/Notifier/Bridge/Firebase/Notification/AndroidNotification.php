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
final class AndroidNotification extends FirebaseOptions
{
    public function channelId(string $channelId): self
    {
        $this->options['android_channel_id'] = $channelId;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->options['icon'] = $icon;

        return $this;
    }

    public function sound(string $sound): self
    {
        $this->options['sound'] = $sound;

        return $this;
    }

    public function tag(string $tag): self
    {
        $this->options['tag'] = $tag;

        return $this;
    }

    public function color(string $color): self
    {
        $this->options['color'] = $color;

        return $this;
    }

    public function clickAction(string $clickAction): self
    {
        $this->options['click_action'] = $clickAction;

        return $this;
    }

    public function bodyLocKey(string $bodyLocKey): self
    {
        $this->options['body_loc_key'] = $bodyLocKey;

        return $this;
    }

    /**
     * @param string[] $bodyLocArgs
     */
    public function bodyLocArgs(array $bodyLocArgs): self
    {
        $this->options['body_loc_args'] = $bodyLocArgs;

        return $this;
    }

    public function titleLocKey(string $titleLocKey): self
    {
        $this->options['title_loc_key'] = $titleLocKey;

        return $this;
    }

    /**
     * @param string[] $titleLocArgs
     */
    public function titleLocArgs(array $titleLocArgs): self
    {
        $this->options['title_loc_args'] = $titleLocArgs;

        return $this;
    }
}
