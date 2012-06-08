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
    public $html_pattern = null;
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
     * Example: /[a-z]+/ would be converted to [a-z]+
     * However, if options are specified, it cannot be converted and this will throw an Exception
     * @return string regex
     * @throws Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function getNonDelimitedPattern() {
        if (preg_match('/^(.)(.*)\1$/', $this->pattern, $matches)) {
            $delimiter = $matches[1];
            // Unescape the delimiter in pattern
            $pattern = str_replace('\\' . $delimiter, $delimiter, $matches[2]);
            return $pattern;
        } else {
            throw new ConstraintDefinitionException("Cannot remove delimiters from pattern '{$this->pattern}'.");
        }
    }

    public function getHtmlPattern() {
        // If html_pattern is specified, use it
        if (!is_null($this->html_pattern)) {
            return empty($this->html_pattern)
                ? false
                : $this->html_pattern;
        } else {
            try {
                return $this->getNonDelimitedPattern();
            } catch (ConstraintDefinitionException $e) {
                // Pattern cannot be converted
                return false;
            }
        }
    }
}
