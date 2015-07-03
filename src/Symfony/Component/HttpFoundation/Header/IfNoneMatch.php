<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Header;


/**
 * Represents an If-None-Match header.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Yosef Deray <yderay@gmail.com>
 */
class IfNoneMatch
{
    private $eTags;

    public function __construct(array $eTags = array())
    {
        $this->eTags = $eTags;
    }

    public function __toString()
    {
        return implode(', ', $this->eTags);
    }

    /**
     * @param string $header
     * @return IfNoneMatch
     */
    public static function fromString($header)
    {
        return new static(preg_split('/\s*,\s*/', $header, null, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @return string[]
     */
    public function getETags()
    {
        return $this->eTags;
    }
}