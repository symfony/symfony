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

use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class TimezoneTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    public function testTimezonesAreSelectable()
    {
        $form = $this->factory->create('timezone');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertArrayHasKey('Africa', $choices);
        $this->assertContains(new ChoiceView('Africa/Kinshasa', 'Africa/Kinshasa', 'Kinshasa'), $choices['Africa'], '', false, false);

        $this->assertArrayHasKey('America', $choices);
        $this->assertContains(new ChoiceView('America/New_York', 'America/New_York', 'New York'), $choices['America'], '', false, false);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testSetInvalidChoices()
    {
        $this->factory->create('timezone', null, array(
            'choices' => 'bad value',
        ));
    }
}
