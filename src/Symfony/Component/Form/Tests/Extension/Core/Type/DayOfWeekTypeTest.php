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
use Symfony\Component\Form\FormView;

class DayOfWeekTypeTest extends TestCase
{
    private $oldLocale;

    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);
        $this->oldLocale = \Locale::getDefault();

        parent::setUp();
    }

    protected function tearDown()
    {
        \Locale::setDefault($this->oldLocale);

        parent::tearDown();
    }

    /**
     * @dataProvider getLocaleDow
     */
    public function testLocaleDow($locale, $firstdow)
    {
        \Locale::setDefault($locale);

        $form = $this->createForm();
        $this->assertSame($firstdow, $form->createView()->vars['choices'][0]->label);
    }

    public function getLocaleDow()
    {
        return array(
            array('en_US', 'Sunday'),
            array('fr_FR', 'lundi'),
        );
    }

    /**
     * @dataProvider getValidValue
     */
    public function testValidValue($value)
    {
        $form = $this->createForm();
        $form->submit($value);
        $this->assertEquals($value, $form->getData());
    }

    public function getValidValue()
    {
        return array(
            array('1'),
            array('7'),
            array(7),
        );
    }

    /**
     * @dataProvider getInvalidValue
     */
    public function testInvalidValue($value)
    {
        $form = $this->createForm();
        $form->submit($value);
        $this->assertNull($form->getData());
    }

    public function getInvalidValue()
    {
        return array(
            array(0),
            array(8),
        );
    }

    /**
     * @dataProvider getLabelFormat
     */
    public function testLabelFormat($labelFormat, $data, $label)
    {
        \Locale::setDefault('en_US');
        $form = $this->createForm(array('label_format' => $labelFormat));
        $form->submit($data);
        $selected = $this->getSelectedChoice($form->createView());

        $this->assertSame($label, $selected->label);
    }

    public function getLabelFormat()
    {
        return array(
            array('e', 1, '2'),
            array('eeee', 1, 'Monday'),
            array('EEEEE', 1, 'M'),
        );
    }

    private function createForm(array $options = array(), $data = null)
    {
        return $this->factory->create('dayofweek', $data, $options);
    }

    private function getSelectedChoice(FormView $view)
    {
        $is_selected = $view->vars['is_selected'];
        $data = $view->vars['data'];

        foreach ($view->vars['choices'] as $choice) {
            if ($is_selected($choice->data, $data)) {
                return $choice;
            }
        }

        return;
    }
}
