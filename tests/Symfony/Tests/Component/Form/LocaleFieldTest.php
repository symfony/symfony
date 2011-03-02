<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\LocaleField;
use Symfony\Component\Form\FormContext;

class LocaleFieldTest extends TestCase
{
    public function testLocalesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $field = $this->factory->getInstance('locale', 'locale');
        $choices = $field->getRenderer()->getVar('choices');

        $this->assertArrayHasKey('en', $choices);
        $this->assertEquals('Englisch', $choices['en']);
        $this->assertArrayHasKey('en_GB', $choices);
        $this->assertEquals('Englisch (Vereinigtes Königreich)', $choices['en_GB']);
        $this->assertArrayHasKey('zh_Hans_MO', $choices);
        $this->assertEquals('Chinesisch (vereinfacht, Sonderverwaltungszone Macao)', $choices['zh_Hans_MO']);
    }
}