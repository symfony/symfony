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

class ServiceDefaults
{
    public function __construct(
        public ?UninstantiableService $service = new UninstantiableService(),
        public Suit $suit = Suit::Hearts,
        public string $name = 'default',
    ) {
    }
}
