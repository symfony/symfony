<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command\Resolver;

use Symfony\Component\Console\Command\Resolver\ClosureCommandResolver;

class ClosureCommandResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testThatItResolvesCommand()
    {
        $command = function () {
            return 42;
        };

        $resolver = new ClosureCommandResolver(function ($name) use ($command) {
            if ($name === 'foo:bar') {
                return $command;
            }
        });

        $this->assertSame($command, $resolver->resolve('foo:bar'));
    }

    /**
     * @expectedException \Symfony\Component\Console\Command\Resolver\CommandResolutionException
     * @expectedExceptionMessage Command "foo:bar" could not be resolved
     */
    public function testThatItThrowsExceptionInCaseOfFailure()
    {
        $resolver = new ClosureCommandResolver(function () {
            return;
        });

        $resolver->resolve('foo:bar');
    }
}
