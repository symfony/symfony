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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataCollectorTranslatorPassTest extends TestCase
{
    private $container;
    private $dataCollectorTranslatorPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dataCollectorTranslatorPass = new DataCollectorTranslatorPass();

        $this->container->setParameter('translator_implementing_bag', Translator::class);
        $this->container->setParameter('translator_not_implementing_bag', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag');

        $this->container->register('translator.data_collector', DataCollectorTranslator::class)
            ->setPublic(false)
            ->setDecoratedService('translator')
            ->setArguments([new Reference('translator.data_collector.inner')])
        ;

        $this->container->register('data_collector.translation', TranslationDataCollector::class)
            ->setArguments([new Reference('translator.data_collector')])
        ;
    }

    /**
     * @dataProvider getImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessKeepsDataCollectorTranslatorIfItImplementsTranslatorBagInterface($class)
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('translator.data_collector'));
    }

    /**
     * @dataProvider getImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessKeepsDataCollectorIfTranslatorImplementsTranslatorBagInterface($class)
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('data_collector.translation'));
    }

    public static function getImplementingTranslatorBagInterfaceTranslatorClassNames()
    {
        return [
            [Translator::class],
            ['%translator_implementing_bag%'],
        ];
    }

    /**
     * @dataProvider getNotImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessRemovesDataCollectorTranslatorIfItDoesNotImplementTranslatorBagInterface($class)
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('translator.data_collector'));
    }

    /**
     * @dataProvider getNotImplementingTranslatorBagInterfaceTranslatorClassNames
     */
    public function testProcessRemovesDataCollectorIfTranslatorDoesNotImplementTranslatorBagInterface($class)
    {
        $this->container->register('translator', $class);

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('data_collector.translation'));
    }

    public static function getNotImplementingTranslatorBagInterfaceTranslatorClassNames()
    {
        return [
            ['Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag'],
            ['%translator_not_implementing_bag%'],
        ];
    }
}

class TranslatorWithTranslatorBag implements TranslatorInterface
{
    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
