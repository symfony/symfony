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


class TimezoneTypeTest extends TypeTestCase
{
    public function testTimezonesAreSelectable()
    {
        $form = $this->factory->create('timezone');
        $view = $form->createView();
        $choices = $view->get('choices');
        $labels = $view->get('choice_labels');

        $this->assertArrayHasKey('Africa', $choices);
        $this->assertContains('Africa/Kinshasa', $choices['Africa']);
        $this->assertEquals('Kinshasa', $labels[array_search('Africa/Kinshasa', $choices['Africa'])]);

        $this->assertArrayHasKey('America', $choices);
        $this->assertContains('America/New_York', $choices['America']);
        $this->assertEquals('New York', $labels[array_search('America/New_York', $choices['America'])]);
    }
}
