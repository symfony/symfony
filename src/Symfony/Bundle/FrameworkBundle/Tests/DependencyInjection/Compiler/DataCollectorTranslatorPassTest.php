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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DataCollectorTranslatorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\TranslatorInterface;

class DataCollectorTranslatorPassTest extends \PHPUnit_Framework_TestCase
{
    private $container;
    private $dataCollectorTranslatorPass;

    protected function setUp()
    {
        $this->container = new ContainerBuilder();
        $this->dataCollectorTranslatorPass = new DataCollectorTranslatorPass();

        $this->container->register('translator.data_collector', 'Symfony\Component\Translation\DataCollectorTranslator')
            ->setPublic(false)
            ->setDecoratedService('translator')
            ->setArguments(array(new Reference('translator.data_collector.inner')))
        ;

        $this->container->register('data_collector.translation', 'Symfony\Component\Translation\DataCollector\TranslationDataCollector')
            ->setArguments(array(new Reference('translator.data_collector')))
        ;
    }

    public function testProcessKeepsDataCollectorTranslatorIfItImplementsTranslatorBagInterface()
    {
        $this->container->register('translator', 'Symfony\Component\Translation\Translator');

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('translator.data_collector'));
    }

    public function testProcessKeepsDataCollectorIfTranslatorImplementsTranslatorBagInterface()
    {
        $this->container->register('translator', 'Symfony\Component\Translation\Translator');

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('data_collector.translation'));
    }

    public function testProcessRemovesDataCollectorTranslatorIfItDoesNotImplementTranslatorBagInterface()
    {
        $this->container->register('translator', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag');

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('translator.data_collector'));
    }

    public function testProcessRemovesDataCollectorIfTranslatorDoesNotImplementTranslatorBagInterface()
    {
        $this->container->register('translator', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\TranslatorWithTranslatorBag');

        $this->dataCollectorTranslatorPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('data_collector.translation'));
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
