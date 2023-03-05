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

    /**
     * @param FormRendererInterface|null $formRenderer
     * @param TranslatorInterface|null   $translator
     */
    public function __construct(ValidatorInterface $validator, /* FormRendererInterface */ $formRenderer = null, /* TranslatorInterface */ $translator = null)
    {
        if (\is_bool($formRenderer)) {
            trigger_deprecation('symfony/form', '6.3', 'The signature of "%s" constructor requires 3 arguments: "ValidatorInterface $validator, FormRendererInterface $formRenderer = null, TranslatorInterface $translator = null". Passing argument $legacyErrorMessages is deprecated.', __CLASS__);
            $formRenderer = $translator;
            $translator = 4 <= \func_num_args() ? func_get_arg(3) : null;
        }

        if (null !== $formRenderer && !$formRenderer instanceof FormRendererInterface) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be an instance of "%s" or null, "%s" given.', __METHOD__, FormRendererInterface::class, get_debug_type($formRenderer)));
        }

        if (null !== $translator && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 3 passed to "%s()" must be an instance of "%s" or null, "%s" given.', __METHOD__, TranslatorInterface::class, get_debug_type($formRenderer)));
        }

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
        $constraintsNormalizer = fn (Options $options, $constraints) => \is_object($constraints) ? [$constraints] : (array) $constraints;

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
