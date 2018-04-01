<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Translation\DependencyInjection\TranslatorPass;

class TranslationPassTest extends TestCase
{
    public function testValidCollector()
    {
        $loader = (new Definition())
            ->addTag('translation.loader', array('alias' => 'xliff', 'legacy-alias' => 'xlf'));

        $reader = new Definition();

        $translator = (new Definition())
            ->setArguments(array(null, null, null, null));

        $container = new ContainerBuilder();
        $container->setDefinition('translator.default', $translator);
        $container->setDefinition('translation.reader', $reader);
        $container->setDefinition('translation.xliff_loader', $loader);

        $pass = new TranslatorPass('translator.default', 'translation.reader');
        $pass->process($container);

        $expectedReader = (new Definition())
            ->addMethodCall('addLoader', array('xliff', new Reference('translation.xliff_loader')))
            ->addMethodCall('addLoader', array('xlf', new Reference('translation.xliff_loader')))
        ;
        $this->assertEquals($expectedReader, $reader);

        $expectedLoader = (new Definition())
            ->addTag('translation.loader', array('alias' => 'xliff', 'legacy-alias' => 'xlf'))
        ;
        $this->assertEquals($expectedLoader, $loader);

        $this->assertSame(array('translation.xliff_loader' => array('xliff', 'xlf')), $translator->getArgument(3));

        $expected = array('translation.xliff_loader' => new ServiceClosureArgument(new Reference('translation.xliff_loader')));
        $this->assertEquals($expected, $container->getDefinition((string) $translator->getArgument(0))->getArgument(0));
    }
}
