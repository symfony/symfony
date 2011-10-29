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


class LocaleTypeTest extends LocalizedTestCase
{
    public function testLocalesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('locale');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertArrayHasKey('en', $choices);
        $this->assertEquals('Englisch', $choices['en']);
        $this->assertArrayHasKey('en_GB', $choices);
        $this->assertEquals('Englisch (Vereinigtes Königreich)', $choices['en_GB']);
        $this->assertArrayHasKey('zh_Hant_MO', $choices);
        $this->assertEquals('Chinesisch (traditionell, Sonderverwaltungszone Macao)', $choices['zh_Hant_MO']);
    }
}
