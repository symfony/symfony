<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Test\TypeTestCase as TestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

class NumberTypeTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        // we test against "de_DE", so we need the full implementation
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_DE');
    }

    public function testDefaultFormatting()
    {
        $form = $this->factory->create('number');
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12345,679', $view->vars['value']);
    }

    public function testDefaultFormattingWithGrouping()
    {
        $form = $this->factory->create('number', null, array('grouping' => true));
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12.345,679', $view->vars['value']);
    }

    public function testDefaultFormattingWithScale()
    {
        $form = $this->factory->create('number', null, array('scale' => 2));
        $form->setData('12345.67890');
        $view = $form->createView();

        $this->assertSame('12345,68', $view->vars['value']);
    }

    public function testDefaultFormattingWithRounding()
    {
        $form = $this->factory->create('number', null, array('scale' => 0, 'rounding_mode' => \NumberFormatter::ROUND_UP));
        $form->setData('12345.54321');
        $view = $form->createView();

        $this->assertSame('12346', $view->vars['value']);
    }
}
