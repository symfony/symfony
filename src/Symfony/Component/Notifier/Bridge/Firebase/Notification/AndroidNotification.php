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

final class AndroidNotification extends FirebaseOptions
{
    /**
     * @return $this
     */
    public function channelId(string $channelId): static
    {
        $this->options['android_channel_id'] = $channelId;

        return $this;
    }

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
    public function sound(string $sound): static
    {
        $this->options['sound'] = $sound;

        return $this;
    }

    /**
     * @return $this
     */
    public function tag(string $tag): static
    {
        $this->options['tag'] = $tag;

        return $this;
    }

    /**
     * @return $this
     */
    public function color(string $color): static
    {
        $this->options['color'] = $color;

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

    /**
     * @return $this
     */
    public function bodyLocKey(string $bodyLocKey): static
    {
        $this->options['body_loc_key'] = $bodyLocKey;

        return $this;
    }

    /**
     * @param string[] $bodyLocArgs
     *
     * @return $this
     */
    public function bodyLocArgs(array $bodyLocArgs): static
    {
        $this->options['body_loc_args'] = $bodyLocArgs;

        return $this;
    }

    /**
     * @return $this
     */
    public function titleLocKey(string $titleLocKey): static
    {
        $this->options['title_loc_key'] = $titleLocKey;

        return $this;
    }

    /**
     * @param string[] $titleLocArgs
     *
     * @return $this
     */
    public function titleLocArgs(array $titleLocArgs): static
    {
        $this->options['title_loc_args'] = $titleLocArgs;

        return $this;
    }
}
