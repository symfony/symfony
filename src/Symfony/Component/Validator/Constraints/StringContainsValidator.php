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

class StringContainsValidator extends AbstractStringContainsValidator
{
    /**
     * {@inheritdoc}
     */
    protected function doValidate(string $value, iterable $texts, AbstractStringContains $constraint): void
    {
        foreach ($texts as $text) {
            $pattern = sprintf('/%s/%s', preg_quote($text, '/'), $constraint->caseSensitive ? '' : 'i');
            if (1 !== preg_match($pattern, $value)) {
                $this->context->buildViolation($constraint->message)
                    ->setCode(StringContains::NOT_CONTAINS_ERROR)
                    ->addViolation();

                return;
            }
        }
    }
}
