<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Kev <https://github.com/symfonyaml>
 * @see https://datatracker.ietf.org/doc/html/rfc2397
 */
class DataUriValidator extends ConstraintValidator
{
    /** maximum number of characters allowed in a error message */
    private const MAX_MESSAGE_VALUE_LENGTH = 30;
    /** data-uri regexp */
    public const PATTERN = '~^
            data:
            (?:\w+\/(?:(?!;).)+)?  # MIME-type
            (?:;[\w\W]*?[^;])*     # parameters
            (;base64)?             # encoding
            ,
            [^$]+                  # data
        $~ixuD';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DataUri) {
            throw new UnexpectedTypeException($constraint, DataUri::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        if ('' === $value) {
            return;
        }

        if (!preg_match(static::PATTERN, $value)) {
            if (strlen($value) > self::MAX_MESSAGE_VALUE_LENGTH) {
                $value = sprintf('%s (truncated)', $this->formatValue(substr($value, 0, self::MAX_MESSAGE_VALUE_LENGTH) . '...'));
            } else {
                $value = $this->formatValue($value);
            }
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setCode(DataUri::INVALID_DATA_URI_ERROR)
                ->addViolation();
        }
    }
}
