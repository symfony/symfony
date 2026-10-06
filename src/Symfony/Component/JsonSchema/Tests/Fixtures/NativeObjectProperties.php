<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use BcMath\Number;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class NativeObjectProperties
{
    public \DateTimeImmutable $createdAt;
    public \DateInterval $duration;
    public Uuid $uuid;
    public Ulid $ulid;
    public Suit $suit = Suit::Hearts;
    public Priority $priority;
    public Color $color;
    public \SplFileInfo $file;
    public Number $amount;
}
