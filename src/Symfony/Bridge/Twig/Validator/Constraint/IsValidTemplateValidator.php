<?php

/**
This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * Twig template validator.
 *
 * @author Gary PEGEOT <g.pegeot@highco-data.fr>
 */
class IsValidTemplateValidator extends ConstraintValidator
{
    /**
     * @var \Twig_Environment
     */
    private $environment;

    /**
     * IsValidTemplateValidator constructor.
     *
     * @param Environment $environment
     */
    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$value) {
            return;
        }

        $realLoader = $this->environment->getLoader();
        try {
            $temporaryLoader = new ArrayLoader(['template' => $value]);
            $this->environment->setLoader($temporaryLoader);
            $nodeTree = $this->environment->parse($this->environment->tokenize(new Source($value, 'template')));
            $this->environment->compile($nodeTree);
            $this->environment->setLoader($realLoader);
        } catch (Error $e) {
            $this->environment->setLoader($realLoader);

            /** @var IsValidTemplate $constraint */
            $this->context->buildViolation($constraint->message)
                ->setParameters([
                    '{{ line }}' => $e->getTemplateLine(),
                    '{{ error }}' => $e->getMessage(),
                ])
                ->addViolation()
            ;
        }
    }
}
