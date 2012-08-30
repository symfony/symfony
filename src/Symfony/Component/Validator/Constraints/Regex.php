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

/**
 * @Annotation
 *
 * @api
 */
class Regex extends Constraint
{
    public $message = 'This value is not valid.';
    public $pattern;
    public $htmlPattern = null;
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
     * Returns htmlPattern if exists or pattern is convertible.
     *
     * @return string|null
     */
    public function getHtmlPattern()
    {
        // If htmlPattern is specified, use it
        if (null !== $this->htmlPattern) {
            return empty($this->htmlPattern)
                ? null
                : $this->htmlPattern;
        }

        return $this->getNonDelimitedPattern();
    }

    /**
     * Convert the htmlPattern to a suitable format for HTML5 pattern.
     * Example: /^[a-z]+$/ would be converted to [a-z]+
     * However, if options are specified, it cannot be converted
     * 
     * Pattern is also ignored if match=false since the pattern should
     * then be reversed before application.
     *
     * @todo reverse pattern in case match=false as per issue #5307
     *
     * @link http://dev.w3.org/html5/spec/single-page.html#the-pattern-attribute
     *
     * @return string|null
     */
    private function getNonDelimitedPattern()
    {
        // If match = false, pattern should not be added to HTML5 validation
        if (!$this->match) {
            return null;
        }
        
        if (preg_match('/^(.)(\^?)(.*?)(\$?)\1$/', $this->pattern, $matches)) {
            $delimiter = $matches[1];
            $start     = empty($matches[2]) ? '.*' : '';
            $pattern   = $matches[3];
            $end       = empty($matches[4]) ? '.*' : '';

            // Unescape the delimiter in pattern
            $pattern = str_replace('\\' . $delimiter, $delimiter, $pattern);

            return $start . $pattern . $end;
        }

        return null;
    }
}
