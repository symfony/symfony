<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

class DummyWithRequiredConstructor
{
    public int $id;

    public function __construct(string $name)
    {
        throw new \LogicException('The constructor must not run.');
    }
}
