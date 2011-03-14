<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\LocaleField;
use Symfony\Component\Form\FormContext;

class LocaleFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testLocalesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $field = new LocaleField('language');
        $choices = $field->getOtherChoices();

        $this->assertArrayHasKey('en', $choices);
        $this->assertEquals('Englisch', $choices['en']);
        $this->assertArrayHasKey('en_GB', $choices);
        $this->assertEquals('Englisch (Vereinigtes Königreich)', $choices['en_GB']);
        $this->assertArrayHasKey('zh_Hans_MO', $choices);
        $this->assertEquals('Chinesisch (vereinfacht, Sonderverwaltungszone Macao)', $choices['zh_Hans_MO']);
    }
}