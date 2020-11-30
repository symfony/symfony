<?php

declare(strict_types=1);

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestTypeTest extends TypeTestCase
{
    public function testInvalidSubmittedData(): void
    {
        $form = $this->factory->create(TestType::class);
        $form->submit('foobar');

        self::assertFalse($form->isSynchronized());
        self::assertFalse($form->isValid());
    }

    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([new TestType()], []),
        ];
    }
}

class TestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($value) {
                if (null === $value) {
                    return $value;
                }

                throw new TransformationFailedException('Error');
            },
            function ($value) {
                if (null === $value) {
                    return $value;
                }

                throw new TransformationFailedException('Error');
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('constraints', []);
    }
}
