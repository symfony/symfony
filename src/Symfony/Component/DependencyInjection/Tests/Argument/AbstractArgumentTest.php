<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;

class AbstractArgumentTest extends TestCase
{
    public function testAbstractArgumentGetters()
    {
        $argument = new AbstractArgument('should be defined by Pass');
        $this->assertSame('should be defined by Pass', $argument->getText());
    }
}
