<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/LocalizedTestCase.php';

class NumberTypeTest extends LocalizedTestCase
{
    public function setUp()
    {
        parent::setUp();

        \Locale::setDefault('de_DE');
    }

    public function testDefaultFormatting()
    {
        $form = $this->factory->create('number');
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12345,679', $view->get('value'));
    }

    public function testDefaultFormattingWithGrouping()
    {
        $form = $this->factory->create('number', null, array('grouping' => true));
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12.345,679', $view->get('value'));
    }

    public function testDefaultFormattingWithPrecision()
    {
        $form = $this->factory->create('number', null, array('precision' => 2));
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12345,68', $view->get('value'));
    }

    public function testDefaultFormattingWithRounding()
    {
        $form = $this->factory->create('number', null, array('precision' => 0, 'rounding_mode' => \NumberFormatter::ROUND_UP));
        $form->setData('12345.54321');
        $view = $form->createView();

        $this->assertSame('12346', $view->get('value'));
    }
}
