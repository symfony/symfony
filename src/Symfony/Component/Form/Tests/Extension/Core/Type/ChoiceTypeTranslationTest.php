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

use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoiceTypeTranslationTest extends TypeTestCase
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\ChoiceType';

    private $choices = [
        'Bernhard' => 'a',
        'Fabien' => 'b',
        'Kris' => 'c',
        'Jon' => 'd',
        'Roman' => 'e',
    ];

    protected function getExtensions()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->any())->method('trans')
            ->willReturnCallback(fn ($key, $params) => strtr(sprintf('Translation of: %s', $key), $params)
            );

        return array_merge(parent::getExtensions(), [new CoreExtension(null, null, $translator)]);
    }

    public function testInvalidMessageAwarenessForMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'invalid_message' => 'You are not able to use value "{{ value }}"',
        ]);

        $form->submit(['My invalid choice']);
        $this->assertEquals(
            "ERROR: Translation of: You are not able to use value \"My invalid choice\"\n",
            (string) $form->getErrors(true)
        );
    }

    public function testInvalidMessageAwarenessForMultipleWithoutScalarOrArrayViewData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'invalid_message' => 'You are not able to use value "{{ value }}"',
        ]);

        $form->submit(new \stdClass());
        $this->assertEquals(
            "ERROR: Translation of: You are not able to use value \"stdClass\"\n",
            (string) $form->getErrors(true)
        );
    }
}
