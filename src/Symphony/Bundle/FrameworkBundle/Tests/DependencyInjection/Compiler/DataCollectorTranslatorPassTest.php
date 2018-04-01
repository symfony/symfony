<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Translation\TranslatorInterface;

class DataCollectorTranslatorPassTest extends TestCase
{
    private $container;
    private $dataCollectorTranslatorPass;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->dataCollectorTranslatorPass = new DataCollectorTranslatorPass();

        $this->container->setParameter('translator_implementing_bag', 'Symphony\Component\Translation\Translator');
        $this->container->setParameter('translator_not_implementing_bag', 'Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag');

        $this->container->register('translator.data_collector', 'Symphony\Component\Translation\DataCollectorTranslator')
            ->setPublic(false)
            ->setDecoratedService('translator')
            ->setArguments(array(new Reference('translator.data_collector.inner')))
        ;

        $this->container->register('data_collector.translation', 'Symphony\Component\Translation\DataCollector\TranslationDataCollector')
            ->setArguments(array(new Reference('translator.data_collector')))
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

    public function getImplementingTranslatorBagInterfaceTranslatorClassNames()
    {
        return array(
            array('Symphony\Component\Translation\Translator'),
            array('%translator_implementing_bag%'),
        );
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

    public function getNotImplementingTranslatorBagInterfaceTranslatorClassNames()
    {
        return array(
            array('Symphony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag'),
            array('%translator_not_implementing_bag%'),
        );
    }
}

class TranslatorWithTranslatorBag implements TranslatorInterface
{
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
    }

    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
    }

    public function setLocale($locale)
    {
    }

    public function getLocale()
    {
    }
}
