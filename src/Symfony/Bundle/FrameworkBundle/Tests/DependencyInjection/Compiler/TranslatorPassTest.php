<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @group legacy
 */
class TranslatorPassTest extends TestCase
{
    public function testValidCollector()
    {
        $loader = (new Definition())
            ->addTag('translation.loader', array('alias' => 'xliff', 'legacy-alias' => 'xlf'));

        $translator = (new Definition())
            ->setArguments(array(null, null, null, null));

        $container = new ContainerBuilder();
        $container->setDefinition('translator.default', $translator);
        $container->setDefinition('translation.loader', $loader);

        $pass = new TranslatorPass();
        $pass->process($container);

        $expected = (new Definition())
            ->addTag('translation.loader', array('alias' => 'xliff', 'legacy-alias' => 'xlf'))
            ->addMethodCall('addLoader', array('xliff', new Reference('translation.loader')))
            ->addMethodCall('addLoader', array('xlf', new Reference('translation.loader')))
        ;
        $this->assertEquals($expected, $loader);

        $this->assertSame(array('translation.loader' => array('xliff', 'xlf')), $translator->getArgument(3));

        $expected = array('translation.loader' => new ServiceClosureArgument(new Reference('translation.loader')));
        $this->assertEquals($expected, $container->getDefinition((string) $translator->getArgument(0))->getArgument(0));
    }
}
