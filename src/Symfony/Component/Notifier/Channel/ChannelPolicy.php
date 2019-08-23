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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
final class ChannelPolicy implements ChannelPolicyInterface
{
    private $policy;

    public function __construct(array $policy)
    {
        $this->policy = $policy;
    }

    public function getChannels(string $importance): array
    {
        if (!isset($this->policy[$importance])) {
            throw new InvalidArgumentException(sprintf('Importance "%s" is not defined in the Policy.', $importance));
        }

        return $this->policy[$importance];
    }
}
