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

class LocaleTypeTest extends TestCase
{
    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    public function testLegacyName()
    {
        $form = $this->factory->create('locale');

        $this->assertSame('locale', $form->getConfig()->getType()->getName());
    }

    public function testLocalesAreSelectable()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\LocaleType');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertContains(new ChoiceView('en', 'en', 'English'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('en_GB', 'en_GB', 'English (United Kingdom)'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('zh_Hant_MO', 'zh_Hant_MO', 'Chinese (Traditional, Macau SAR China)'), $choices, '', false, false);
    }
}
