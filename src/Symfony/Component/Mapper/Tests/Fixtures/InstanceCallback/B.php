<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mapper\Tests\Fixtures\InstanceCallback;

class B
{
    public ?string $name = null;

    public function __construct(private readonly int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public static function newInstance(): self
    {
        return new self(1);
    }
}
