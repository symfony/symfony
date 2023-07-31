<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Factory;

use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;

class TimeBasedUuidFactory
{
    /**
     * @var class-string<UuidV1|UuidV6|UuidV7>
     */
    private string $class;
    private ?Uuid $node;

    public function __construct(string $class, Uuid $node = null)
    {
        $this->class = $class;
        $this->node = $node;
    }

    public function create(\DateTimeInterface $time = null): Uuid&TimeBasedUidInterface
    {
        $class = $this->class;

        if (null === $time && null === $this->node) {
            return new $class();
        }

        return new $class($class::generate($time, $this->node));
    }
}
