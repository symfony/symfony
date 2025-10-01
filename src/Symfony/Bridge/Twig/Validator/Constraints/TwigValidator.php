<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
class TwigValidator extends ConstraintValidator
{
    public function __construct(private Environment $twig)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Twig) {
            throw new UnexpectedTypeException($constraint, Twig::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        $realLoader = $this->twig->getLoader();
        try {
            $temporaryLoader = new ArrayLoader([$value]);
            $this->twig->setLoader($temporaryLoader);

            if (!$constraint->skipDeprecations) {
                $prevErrorHandler = set_error_handler(static function ($level, $message, $file, $line) use (&$prevErrorHandler) {
                    if (\E_USER_DEPRECATED !== $level) {
                        return $prevErrorHandler ? $prevErrorHandler($level, $message, $file, $line) : false;
                    }

                    $templateLine = 0;
                    if (preg_match('/ at line (\d+)[ .]/', $message, $matches)) {
                        $templateLine = $matches[1];
                    }

                    throw new Error($message, $templateLine);
                });
            }

            try {
                $this->twig->parse($this->twig->tokenize(new Source($value, '')));
            } finally {
                if (!$constraint->skipDeprecations) {
                    restore_error_handler();
                }
            }
        } catch (Error $e) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ error }}', $e->getMessage())
                ->setParameter('{{ line }}', $e->getTemplateLine())
                ->setCode(Twig::INVALID_TWIG_ERROR)
                ->addViolation();
        } finally {
            $this->twig->setLoader($realLoader);
        }
    }
}
