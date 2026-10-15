<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Transport;

use Symfony\Component\Messenger\Stamp\SenderStampInterface;

/**
 * @author Nikolay Kurdoglo <nikolay.kurdoglo@icloud.com>
 */
final class BeanstalkdTubeStamp implements SenderStampInterface
{
    public function __construct(
        public readonly string $tube,
    ) {
    }
}
