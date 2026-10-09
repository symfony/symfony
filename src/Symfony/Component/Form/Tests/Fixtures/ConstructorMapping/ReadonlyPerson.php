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

final readonly class ReadonlyPerson
{
    public function __construct(
        public string $name,
        public ?\DateTimeImmutable $birthDate = null,
        public array $tags = [],
        public ?ReadonlyAddress $address = null,
    ) {
    }
}
