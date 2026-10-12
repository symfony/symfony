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

abstract class PrivateReadonlyParent
{
    public function __construct(
        private readonly string $code,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
