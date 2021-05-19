<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Channel;

interface ChannelPolicyInterface
{
    /**
     * @return string[]
     */
    public function getChannels(string $importance): array;
}
