<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader\Configurator;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

class ContainerConfiguratorTest extends TestCase
{
    public function testImportForwardsExcludeAndIgnoreErrors()
    {
        $container = new ContainerBuilder();

        $loader = $this->getMockBuilder(PhpFileLoader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setCurrentDir', 'import'])
            ->getMock();

        $path = '/path/file.php';
        $expectedDir = '/path';

        $resource = 'services/*.php';
        $type = null;
        $ignoreErrors = 'not_found';
        $exclude = ['services/excluded/*'];

        $loader->expects($this->once())
            ->method('setCurrentDir')
            ->with($this->equalTo($expectedDir));

        $loader->expects($this->once())
            ->method('import')
            ->with(
                $this->equalTo($resource),
                $this->equalTo($type),
                $this->equalTo($ignoreErrors),
                $this->equalTo($path),
                $this->equalTo($exclude)
            );

        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, $path, $path);
        $configurator->import($resource, $type, $ignoreErrors, $exclude);
    }

    public function testTaggedIteratorAcceptsGetPrefixedExclude()
    {
        $argument = tagged_iterator('foo', null, 'get_something');

        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame(['get_something'], $argument->getExclude());
    }

    public function testTaggedLocatorAcceptsGetPrefixedExclude()
    {
        $argument = tagged_locator('foo', null, 'get_something');

        $this->assertInstanceOf(ServiceLocatorArgument::class, $argument);
        $this->assertSame(['get_something'], $argument->getTaggedIteratorArgument()->getExclude());
    }

    public function testTaggedIteratorAcceptsNullExclude()
    {
        $argument = tagged_iterator('foo', null, null, true);

        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame([], $argument->getExclude());
        $this->assertTrue($argument->excludeSelf());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testTaggedIteratorBcDetectsNullPositionalDefaultPriorityMethod()
    {
        $argument = tagged_iterator('foo', 'idx', 'fetchId', null);

        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertSame('fetchId', $argument->getDefaultIndexMethod(false));
        $this->assertSame([], $argument->getExclude());
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testTaggedLocatorBcDetectsNullPositionalDefaultPriorityMethod()
    {
        $argument = tagged_locator('foo', 'idx', 'fetchId', null);

        $this->assertInstanceOf(ServiceLocatorArgument::class, $argument);
        $this->assertSame('fetchId', $argument->getTaggedIteratorArgument()->getDefaultIndexMethod(false));
        $this->assertSame([], $argument->getTaggedIteratorArgument()->getExclude());
    }
}
