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

use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class LanguageTypeTest extends LocalizedTestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('language');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertContains(new ChoiceView('en', 'en', 'Englisch'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('en_GB', 'en_GB', 'Britisches Englisch'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('en_US', 'en_US', 'Amerikanisches Englisch'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('fr', 'fr', 'Französisch'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('my', 'my', 'Birmanisch'), $choices, '', false, false);
    }

    public function testMultipleLanguagesIsNotIncluded()
    {
        $form = $this->factory->create('language', 'language');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertNotContains(new ChoiceView('mul', 'mul', 'Mehrsprachig'), $choices, '', false, false);
    }
}
