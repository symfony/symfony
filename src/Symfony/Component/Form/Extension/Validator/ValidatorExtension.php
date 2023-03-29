<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Extension supporting the Symfony Validator component in forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorExtension extends AbstractExtension
{
    private ValidatorInterface $validator;
    private ?FormRendererInterface $formRenderer;
    private ?TranslatorInterface $translator;

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
            throw new \TypeError(sprintf('Argument 3 passed to "%s()" must be an instance of "%s" or null, "%s" given.', __METHOD__, TranslatorInterface::class, get_debug_type($translator)));
        }

        $metadata = $validator->getMetadataFor(\Symfony\Component\Form\Form::class);

        // Register the form constraints in the validator programmatically.
        // This functionality is required when using the Form component without
        // the DIC, where the XML file is loaded automatically. Thus the following
        // code must be kept synchronized with validation.xml

        /* @var $metadata ClassMetadata */
        $metadata->addConstraint(new Form());
        $metadata->addConstraint(new Traverse(false));

        $this->validator = $validator;
        $this->formRenderer = $formRenderer;
        $this->translator = $translator;
    }

    public function loadTypeGuesser(): ?FormTypeGuesserInterface
    {
        return new ValidatorTypeGuesser($this->validator);
    }

    protected function loadTypeExtensions(): array
    {
        return [
            new Type\FormTypeValidatorExtension($this->validator, $this->formRenderer, $this->translator),
            new Type\RepeatedTypeValidatorExtension(),
            new Type\SubmitTypeValidatorExtension(),
        ];
    }
}
