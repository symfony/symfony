<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Expression;

@trigger_error('The '.__NAMESPACE__.'\Glob class is deprecated since Symfony 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

use Symfony\Component\Finder\Glob as FinderGlob;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Glob implements ValueInterface
{
    private $pattern;

    /**
     * @param string $pattern
     */
    public function __construct($pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        return $this->pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function renderPattern()
    {
        return $this->pattern;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return Expression::TYPE_GLOB;
    }

    /**
     * {@inheritdoc}
     */
    public function isCaseSensitive()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function prepend($expr)
    {
        $this->pattern = $expr.$this->pattern;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function append($expr)
    {
        $this->pattern .= $expr;

        return $this;
    }

    /**
     * Tests if glob is expandable ("*.{a,b}" syntax).
     *
     * @return bool
     */
    public function isExpandable()
    {
        return false !== strpos($this->pattern, '{')
            && false !== strpos($this->pattern, '}');
    }

    /**
     * @param bool $strictLeadingDot
     * @param bool $strictWildcardSlash
     *
     * @return Regex
     */
    public function toRegex($strictLeadingDot = true, $strictWildcardSlash = true)
    {
        $regex = FinderGlob::toRegex($this->pattern, $strictLeadingDot, $strictWildcardSlash, '');

        return new Regex($regex);
    }
}
