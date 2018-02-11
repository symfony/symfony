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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslatorPass;

class TranslatorPassTest extends TestCase
{
    public function testValidCollector()
    {
        $container = new ContainerBuilder();
        $container->register('translator.default')
            ->setArguments(array(null, null, array()));
        $translationLoaderDefinition = $container->register('translation.loader');
        $container->register('xliff')
            ->addTag('translation.loader', array('alias' => 'xliff', 'legacy-alias' => 'xlf'));

        $pass = new TranslatorPass();
        $pass->process($container);

        $this->assertEquals(
            array(
                array('addLoader', array('xliff', new Reference('xliff'))),
                array('addLoader', array('xlf', new Reference('xliff'))),
            ),
            $translationLoaderDefinition->getMethodCalls()
        );
    }
}
