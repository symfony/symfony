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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @api
 */
class Regex extends Constraint
{
    public $message = 'This value is not valid.';
    public $pattern;
    public $match = true;

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'pattern';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('pattern');
    }

    /**
     * Sometimes, like when converting to HTML5 pattern attribute, the regex is needed without the delimiters
     * Example: /[a-z]+/i would be converted to [a-z]+
     * However, if options are specified, it cannot be converted and this will throw an Exception
     * @return string regex
     * @throws Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function getNonDelimitedPattern() {
        if (preg_match('/^(.)(.*)\1$/', $this->pattern, $matches)) {
            return $matches[2];
        } else {
            throw new ConstraintDefinitionException("Cannot remove delimiters from pattern '{$this->pattern}'.");
        }
    }
}
