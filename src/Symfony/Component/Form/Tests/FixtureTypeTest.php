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

class FixtureTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    /**
     * @dataProvider provideTypeClassBlockPrefix
     */
    public function testUniqueBlockPrefixes($typeClass, $blockPrefixes)
    {
        $form = $this->factory->create($typeClass);
        $view = $form->createView();
        $this->assertEquals($view->vars['block_prefixes'], $blockPrefixes);
    }

    /**
     * @return array
     */
    public function provideTypeClassBlockPrefix()
    {
        return array(
            array(__NAMESPACE__.'\Fixtures\Foo', ['form','foo','_foo']),
            array(__NAMESPACE__.'\Fixtures\Type', ['form', 'type', '_type']),
            array(__NAMESPACE__.'\Fixtures\FooBarHTMLType',['form', 'foo_bar_html', '_foo_bar_html']),
            array(__NAMESPACE__.'\Fixtures\Foo1Bar2Type', ['form', 'foo1_bar2', '_foo1_bar2']),
            array(__NAMESPACE__.'\Fixtures\OtherType\Foo1Bar2Type', ['form', 'foo1_bar2', '_foo1_bar2']),
            array(__NAMESPACE__.'\Fixtures\FBooType', ['form', 'f_boo', '_f_boo']),
        );
    }
}
