<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Test\CommandsResolver;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tests\Fixtures\CustomCommandResolver;
use Symfony\Component\Console\Tests\Fixtures\LazyTestCommand;

/**
 * @author Ivan Shcherbak <dev@funivan.com>
 */
class CommandResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testLazyCommandResolver()
    {
        $resolver = new CustomCommandResolver();
        $names = $resolver->getAllNames();
        $this->assertEquals(array('lazyTest'), $names);
    }

    public function testLazyLoadingCommands()
    {
        $application = new Application('foo', 'bar', new CustomCommandResolver());
        $command = $application->get('lazyTest');
        $this->assertNotEmpty($command);
        $this->assertTrue($command instanceof LazyTestCommand);
    }
}
