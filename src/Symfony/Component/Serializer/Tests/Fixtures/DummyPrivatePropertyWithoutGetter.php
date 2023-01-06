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

final class DummyPrivatePropertyWithoutGetter
{
    private $foo = 'foo';
    private $bar = 'bar';

    public function getBar()
    {
        return $this->bar;
    }
}
