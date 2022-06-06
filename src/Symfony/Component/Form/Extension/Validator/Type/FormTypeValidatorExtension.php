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
    private $validator;
    private $violationMapper;
    private $legacyErrorMessages;

    public function __construct(ValidatorInterface $validator, bool $legacyErrorMessages = true, FormRendererInterface $formRenderer = null, TranslatorInterface $translator = null)
    {
        $this->validator = $validator;
        $this->violationMapper = new ViolationMapper($formRenderer, $translator);
        $this->legacyErrorMessages = $legacyErrorMessages;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new ValidationListener($this->validator, $this->violationMapper));
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        // Constraint should always be converted to an array
        $constraintsNormalizer = function (Options $options, $constraints) {
            return \is_object($constraints) ? [$constraints] : (array) $constraints;
        };

        $resolver->setDefaults([
            'error_mapping' => [],
            'constraints' => [],
            'invalid_message' => 'This value is not valid.',
            'invalid_message_parameters' => [],
            'legacy_error_messages' => $this->legacyErrorMessages,
            'allow_extra_fields' => false,
            'extra_fields_message' => 'This form should not contain extra fields.',
        ]);
        $resolver->setAllowedTypes('constraints', [Constraint::class, Constraint::class.'[]']);
        $resolver->setAllowedTypes('legacy_error_messages', 'bool');
        $resolver->setDeprecated('legacy_error_messages', 'symfony/form', '5.2', function (Options $options, $value) {
            if (true === $value) {
                return 'Setting the "legacy_error_messages" option to "true" is deprecated. It will be disabled in Symfony 6.0.';
            }

            return '';
        });

        $resolver->setNormalizer('constraints', $constraintsNormalizer);
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
