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

class ContainsWordValidator extends AbstractStringContainsValidator
{
    /**
     * {@inheritdoc}
     */
    protected function doValidate(string $value, iterable $texts, AbstractStringContains $constraint): void
    {
        foreach ($texts as $text) {
            $pattern = sprintf('/\\b%s\\b/%s', preg_quote($text, '/'), $constraint->caseSensitive ? '' : 'i');
            if (1 !== preg_match($pattern, $value)) {
                $this->context->buildViolation($constraint->message)
                    ->setCode(ContainsWord::NOT_CONTAINS_WORD_ERROR)
                    ->addViolation();

                return;
            }
        }
    }
}
