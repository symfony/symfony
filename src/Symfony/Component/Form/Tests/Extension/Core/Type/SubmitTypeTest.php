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

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\Form\Test\TypeTestCase as TestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitTypeTest extends TestCase
{
    public function testCreateSubmitButtonInstances()
    {
        $this->assertInstanceOf(SubmitButton::class, $this->factory->create(SubmitType::class));
    }

    public function testNotClickedByDefault()
    {
        $button = $this->factory->create(SubmitType::class);

        $this->assertFalse($button->isClicked());
    }

    public function testNotClickedIfSubmittedWithNull()
    {
        $button = $this->factory->create(SubmitType::class);
        $button->submit(null);

        $this->assertFalse($button->isClicked());
    }

    public function testClickedIfSubmittedWithEmptyString()
    {
        $button = $this->factory->create(SubmitType::class);
        $button->submit('');

        $this->assertTrue($button->isClicked());
    }

    public function testClickedIfSubmittedWithUnemptyString()
    {
        $button = $this->factory->create(SubmitType::class);
        $button->submit('foo');

        $this->assertTrue($button->isClicked());
    }

    public function testSubmitCanBeAddedToForm()
    {
        $form = $this->factory
            ->createBuilder(FormType::class)
            ->getForm();

        $this->assertSame($form, $form->add('send', SubmitType::class));
    }
}
