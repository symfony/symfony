<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;

class FormErrorNormalizerTest extends TestCase
{
    /**
     * @var FormErrorNormalizer
     */
    private $normalizer;

    /**
     * @var FormInterface
     */
    private $form;

    protected function setUp(): void
    {
        $this->normalizer = new FormErrorNormalizer();

        $this->form = $this->createMock(FormInterface::class);
        $this->form->method('isSubmitted')->willReturn(true);
        $this->form->method('all')->willReturn([]);

        $this->form->method('getErrors')
            ->willReturn(new FormErrorIterator($this->form, [
                new FormError('a', 'b', ['c', 'd'], 5, 'f'),
                new FormError(1, 2, [3, 4], 5, 6),
            ])
            );
    }

    public function testSupportsNormalizationWithWrongClass()
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testSupportsNormalizationWithNotSubmittedForm()
    {
        $form = $this->createMock(FormInterface::class);
        $this->assertFalse($this->normalizer->supportsNormalization($form));
    }

    public function testSupportsNormalizationWithValidForm()
    {
        $this->assertTrue($this->normalizer->supportsNormalization($this->form));
    }

    public function testNormalize()
    {
        $expected = [
            'code' => null,
            'title' => 'Validation Failed',
            'type' => 'https://symfony.com/errors/form',
            'errors' => [
                [
                    'message' => 'a',
                    'cause' => 'f',
                ],
                [
                    'message' => '1',
                    'cause' => 6,
                ],
            ],
        ];

        $this->assertEquals($expected, $this->normalizer->normalize($this->form));
    }

    public function testNormalizeWithChildren()
    {
        $exptected = [
            'code' => null,
            'title' => 'Validation Failed',
            'type' => 'https://symfony.com/errors/form',
            'errors' => [
                [
                    'message' => 'a',
                    'cause' => null,
                ],
            ],
            'children' => [
                'form1' => [
                    'errors' => [
                        [
                            'message' => 'b',
                            'cause' => null,
                        ],
                    ],
                ],
                'form2' => [
                    'errors' => [
                        [
                            'message' => 'c',
                            'cause' => null,
                        ],
                    ],
                    'children' => [
                        'form3' => [
                            'errors' => [
                                [
                                    'message' => 'd',
                                    'cause' => null,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $form = clone $form1 = clone $form2 = clone $form3 = $this->createMock(FormInterface::class);

        $form1->method('getErrors')
            ->willReturn(new FormErrorIterator($form1, [
                new FormError('b'),
            ])
            );
        $form1->method('getName')->willReturn('form1');

        $form2->method('getErrors')
            ->willReturn(new FormErrorIterator($form1, [
                new FormError('c'),
            ])
            );
        $form2->method('getName')->willReturn('form2');

        $form3->method('getErrors')
            ->willReturn(new FormErrorIterator($form1, [
                new FormError('d'),
            ])
            );
        $form3->method('getName')->willReturn('form3');

        $form2->method('all')->willReturn([$form3]);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('all')->willReturn([$form1, $form2]);
        $form->method('getErrors')
            ->willReturn(new FormErrorIterator($form, [
                new FormError('a'),
            ])
            );

        $this->assertEquals($exptected, $this->normalizer->normalize($form));
    }
}
