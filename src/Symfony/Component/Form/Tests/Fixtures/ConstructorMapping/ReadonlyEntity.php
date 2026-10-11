<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\ConstructorMapping;

class ReadonlyEntity
{
    private ?int $id = null;

    public function __construct(
        public readonly string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
