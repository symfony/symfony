<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\EventListener\ValidationListener;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormTypeValidatorExtension extends BaseValidatorExtension
{
    private ValidatorInterface $validator;
    private ViolationMapper $violationMapper;
    private bool $legacyErrorMessages;

    public function __construct(ValidatorInterface $validator, bool $legacyErrorMessages = true, FormRendererInterface $formRenderer = null, TranslatorInterface $translator = null)
    {
        $this->validator = $validator;
        $this->violationMapper = new ViolationMapper($formRenderer, $translator);
    }

    /**
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new ValidationListener($this->validator, $this->violationMapper));
    }

    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        // Constraint should always be converted to an array
        $constraintsNormalizer = static fn (Options $options, $constraints) => \is_object($constraints) ? [$constraints] : (array) $constraints;

        $resolver->setDefaults([
            'error_mapping' => [],
            'constraints' => [],
            'invalid_message' => 'This value is not valid.',
            'invalid_message_parameters' => [],
            'allow_extra_fields' => false,
            'extra_fields_message' => 'This form should not contain extra fields.',
        ]);
        $resolver->setAllowedTypes('constraints', [Constraint::class, Constraint::class.'[]']);
        $resolver->setNormalizer('constraints', $constraintsNormalizer);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
