<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormBuilder;

class AbstractExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasType()
    {
        $loader = new TestExtension();
        $this->assertTrue($loader->hasType('foo'));
        $this->assertFalse($loader->hasType('bar'));
    }

    public function testGetType()
    {
        $loader = new TestExtension(array($type));
        $this->assertInstanceOf(__NAMESPACE__.'\TestType', $loader->getType('foo'));
        $this->assertSame($loader->getType('foo'), $loader->getType('foo'));
    }
}

class TestType implements FormTypeInterface
{
    public function getName()
    {
        return 'foo';
    }

    function buildForm(FormBuilder $builder, array $options) {}

    function buildView(FormView $view, FormInterface $form) {}

    function buildViewBottomUp(FormView $view, FormInterface $form) {}

    function createBuilder($name, FormFactoryInterface $factory, array $options) {}

    function getDefaultOptions(array $options) {}

    function getParent(array $options) {}

    function setExtensions(array $extensions) {}

    function getExtensions() {}
}

class TestExtension extends AbstractExtension
{
    protected function loadTypes()
    {
        return array(new TestType());
    }

    protected function loadTypeGuesser()
    {
    }
}