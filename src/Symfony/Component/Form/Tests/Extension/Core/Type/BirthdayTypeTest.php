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

class BirthdayTypeTest extends LocalizedTestCase
{
    protected function setUp()
    {
        parent::setUp();

        \Locale::setDefault('pl_PL');
    }

    public function testThatHasDefaultValues()
    {
        $form = $this->factory->create('birthday', null, array());

        $view = $form->createView();

        $this->assertCount(121, $view->getChild('year')->get('choices'));
    }
}
