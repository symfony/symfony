<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\DataClassType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataClassTypeTest extends TestCase
{
    private DataClassType $type;

    protected function setUp(): void
    {
        $this->type = new DataClassType('App\SomeData', 'App\ParentType', 'some_data', ['foo' => [null, ['label' => 'Foo']]], ['label' => 'Bar']);
    }

    public function testGetParent()
    {
        $this->assertSame('App\ParentType', $this->type->getParent());
    }

    public function testConfigureOptions()
    {
        $resolver = new OptionsResolver();
        $this->type->configureOptions($resolver);

        $this->assertSame(['label' => 'Bar', 'data_class' => 'App\SomeData'], $resolver->resolve());
    }

    public function testBuildForm()
    {
        ($builder = $this->createMock(FormBuilderInterface::class))
            ->expects($this->once())
            ->method('add')
            ->with('foo', null, ['label' => 'Foo']);

        $this->type->buildForm($builder, []);
    }

    public function testGetBlockPrefix()
    {
        $this->assertSame('some_data', $this->type->getBlockPrefix());
    }

    public function testGetDataClass()
    {
        $this->assertSame('App\SomeData', $this->type->getDataClass());
    }
}
