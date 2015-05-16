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
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Intl\Util\IntlTestHelper;

class LanguageTypeTest extends TestCase
{
    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    public function testCountriesAreSelectable()
    {
        $form = $this->factory->create('language');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertContains(new ChoiceView('English', 'en', 'en'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('British English', 'en_GB', 'en_GB'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('American English', 'en_US', 'en_US'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('French', 'fr', 'fr'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('Burmese', 'my', 'my'), $choices, '', false, false);
    }

    public function testMultipleLanguagesIsNotIncluded()
    {
        $form = $this->factory->create('language', array('data' => 'language'));
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertNotContains(new ChoiceView('Mehrsprachig', 'mul', 'mul'), $choices, '', false, false);
    }
}
