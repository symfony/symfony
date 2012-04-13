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

class SearchTypeTest extends TypeTestCase
{
    public function testThatCanUseSearchType()
    {
        $form = $this->factory->create('search');

        $form->bind('searched phrase');
        $view = $form->createView();

        $this->assertEquals('searched phrase', $form->getData());
        $this->assertEquals('searched phrase', $view->get('value'));
    }
}
