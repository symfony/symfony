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

class NonSerializableProperties
{
    public string $name;
    /** @var callable */
    public $handler;
    /** @var resource */
    public $stream;
    /** @var callable|null */
    public $optionalHandler;
    /** @var int|callable */
    public $retryPolicy;
}
