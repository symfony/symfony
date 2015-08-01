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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitTypeTest extends TestCase
{
    public function testLegacyName()
    {
        $form = $this->factory->create('submit');

        $this->assertSame('submit', $form->getConfig()->getType()->getName());
    }

    public function testCreateSubmitButtonInstances()
    {
        $this->assertInstanceOf('Symfony\Component\Form\SubmitButton', $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType'));
    }

    public function testNotClickedByDefault()
    {
        $button = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType');

        $this->assertFalse($button->isClicked());
    }

    public function testNotClickedIfSubmittedWithNull()
    {
        $button = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $button->submit(null);

        $this->assertFalse($button->isClicked());
    }

    public function testClickedIfSubmittedWithEmptyString()
    {
        $button = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $button->submit('');

        $this->assertTrue($button->isClicked());
    }

    public function testClickedIfSubmittedWithUnemptyString()
    {
        $button = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType');
        $button->submit('foo');

        $this->assertTrue($button->isClicked());
    }

    public function testSubmitCanBeAddedToForm()
    {
        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType')
            ->getForm();

        $this->assertSame($form, $form->add('send', 'Symfony\Component\Form\Extension\Core\Type\SubmitType'));
    }
}
