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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SubmitTypeTest extends ButtonTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\SubmitType';

    public function testCreateSubmitButtonInstances(): void
    {
        $this->assertInstanceOf('Symfony\Component\Form\SubmitButton', $this->factory->create(static::TESTED_TYPE));
    }

    public function testNotClickedByDefault(): void
    {
        $button = $this->factory->create(static::TESTED_TYPE);

        $this->assertFalse($button->isClicked());
    }

    public function testNotClickedIfSubmittedWithNull(): void
    {
        $button = $this->factory->create(static::TESTED_TYPE);
        $button->submit(null);

        $this->assertFalse($button->isClicked());
    }

    public function testClickedIfSubmittedWithEmptyString(): void
    {
        $button = $this->factory->create(static::TESTED_TYPE);
        $button->submit('');

        $this->assertTrue($button->isClicked());
    }

    public function testClickedIfSubmittedWithUnemptyString(): void
    {
        $button = $this->factory->create(static::TESTED_TYPE);
        $button->submit('foo');

        $this->assertTrue($button->isClicked());
    }

    public function testSubmitCanBeAddedToForm(): void
    {
        $form = $this->factory
            ->createBuilder(FormTypeTest::TESTED_TYPE)
            ->getForm();

        $this->assertSame($form, $form->add('send', static::TESTED_TYPE));
    }
}
