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

/**
 * Validates whether the value is a valid UUID per RFC 4122.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 *
 * @see http://tools.ietf.org/html/rfc4122
 * @see https://en.wikipedia.org/wiki/Universally_unique_identifier
 */
class UuidValidator extends ConstraintValidator
{
    /**
     * Regular expression which verifies allowed characters and the proper format.
     *
     * The strict pattern matches UUIDs like this: xxxxxxxx-xxxx-Mxxx-Nxxx-xxxxxxxxxxxx
     * Roughly speaking: x = any hexadecimal character, M = any allowed version, N = any allowed variant.
     */
    const STRICT_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[%s][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i';

    /**
     * The loose pattern validates similar yet non-compliant UUIDs.
     *
     * Dashes are completely optional.  If present, they should only appear between every fourth character.
     * The value can also be wrapped with characters like []{} for backwards-compatibility with other systems.
     * Neither the version nor the variant is validated by this pattern.
     */
    const LOOSE_PATTERN = '/^[a-f0-9]{4}(?:-?[a-f0-9]{4}){7}$/i';

    /**
     * Properly-formatted UUIDs contain 32 hex digits, separated by 4 dashes.
     * We can use this fact to avoid performing a preg_match on strings of other sizes.
     */
    const STRICT_UUID_LENGTH = 36;

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        if ($constraint->strict) {
            // Insert the allowed versions into the regular expression
            $pattern = sprintf(static::STRICT_PATTERN, implode('', $constraint->versions));

            if (strlen($value) !== static::STRICT_UUID_LENGTH || !preg_match($pattern, $value)) {
                $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
            }
        } else {
            // Trim any wrapping characters like [] or {} used by some legacy systems
            $value = trim($value, '[]{}');

            if (!preg_match(static::LOOSE_PATTERN, $value)) {
                $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
            }
        }
    }
}
