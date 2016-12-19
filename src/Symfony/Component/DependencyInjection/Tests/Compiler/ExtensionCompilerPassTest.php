<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Compiler\ExtensionCompilerPass;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class ExtensionCompilerPassTest extends \PHPUnit_Framework_TestCase
{
    private $container;
    private $pass;

    protected function setUp()
    {
        $this->container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerBuilder')->getMock();
        $this->pass = new ExtensionCompilerPass();
    }

    public function testProcess()
    {
        $extension1 = $this->createExtensionMock(true);
        $extension1->expects($this->once())->method('process');
        $extension2 = $this->createExtensionMock(false);
        $extension3 = $this->createExtensionMock(false);
        $extension4 = $this->createExtensionMock(true);
        $extension4->expects($this->once())->method('process');

        $this->container->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array($extension1, $extension2, $extension3, $extension4)))
        ;

        $this->pass->process($this->container);
    }

    private function createExtensionMock($hasInlineCompile)
    {
        return $this->getMockBuilder('Symfony\Component\DependencyInjection\\'.(
            $hasInlineCompile
            ? 'Compiler\CompilerPassInterface'
            : 'Extension\ExtensionInterface'
        ))->getMock();
    }
}
