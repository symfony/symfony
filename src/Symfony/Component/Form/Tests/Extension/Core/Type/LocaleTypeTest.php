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
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Util\IntlTestHelper;

class LocaleTypeTest extends TypeTestCase
{
    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    /**
     * @dataProvider localeProvider
     */
    public function testLocalesAreSelectable($locale)
    {
        \Locale::setDefault($locale);

        $form = $this->factory->create('locale');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        $this->assertContains(new ChoiceView('en', 'en', 'English'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('en_GB', 'en_GB', 'English (United Kingdom)'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('zh_Hant_MO', 'zh_Hant_MO', 'Chinese (Traditional, Macau SAR China)'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('ja', 'ja', 'Japanese'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('ja_JP', 'ja_JP', 'Japanese (Japan)'), $choices, '', false, false);
    }

    public function localeProvider()
    {
        return array(
            array('en'),
            array('en_US'),
            array('en_CA'),
            array('en_GB')
        );
    }
}
