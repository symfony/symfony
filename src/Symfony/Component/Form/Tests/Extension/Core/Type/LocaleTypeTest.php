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

class LocaleTypeTest extends LocalizedTestCase
{
    public function testLocalesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('locale');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertContains(new ChoiceView('en', 'en', 'Englisch'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('en_GB', 'en_GB', 'Englisch (Vereinigtes Königreich)'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('zh_Hant_MO', 'zh_Hant_MO', 'Chinesisch (traditionell, Sonderverwaltungszone Macao)'), $choices, '', false, false);
    }
}
