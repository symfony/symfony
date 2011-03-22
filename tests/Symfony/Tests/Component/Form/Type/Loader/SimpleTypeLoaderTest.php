<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type\Loader;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Type\FieldTypeInterface;
use Symfony\Component\Form\Type\Loader\SimpleTypeLoader;

class SimpleTypeLoaderTest extends \PHPUnit_Framework_TestCase
{
    private $loader;

    protected function setUp()
    {
        $this->loader = new SimpleTypeLoader();
    }

    public function testHasType()
    {
        $this->assertTrue($this->loader->hasType(__NAMESPACE__.'\TestType'));
        $this->assertFalse($this->loader->hasType(__NAMESPACE__.'\FooType'));
    }

    public function testGetType()
    {
        $this->assertEquals(new TestType(), $this->loader->getType(__NAMESPACE__.'\TestType'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeLoaderException
     */
    public function testGetInvalidTypeThrowsException()
    {
        $this->loader->getType(__NAMESPACE__.'\FooType');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeLoaderException
     */
    public function testGetClassThatDoesNotImplementInterface()
    {
        $this->loader->getType(__NAMESPACE__.'\InvalidType');
    }
}

class InvalidType
{
}

class TestType implements FieldTypeInterface
{
    function configure(FormBuilder $builder, array $options) {}

    function createBuilder(array $options) {}

    function getDefaultOptions(array $options) {}

    function getParent(array $options) {}

    function getName() {}
}