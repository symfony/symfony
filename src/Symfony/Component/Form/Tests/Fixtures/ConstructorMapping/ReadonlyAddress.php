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

final readonly class ReadonlyAddress
{
    public function __construct(
        public string $city,
        public string $zipCode = '',
    ) {
    }
}
