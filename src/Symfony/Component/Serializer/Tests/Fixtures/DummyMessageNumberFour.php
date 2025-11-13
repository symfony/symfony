<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Ignore;

abstract class SomeAbstract {
    #[Ignore]
    public function getDescription()
    {
        return 'Hello, World!';
    }
}

class DummyMessageNumberFour extends SomeAbstract implements DummyMessageInterface
{
    public function __construct(public $one)
    {
    }
}
