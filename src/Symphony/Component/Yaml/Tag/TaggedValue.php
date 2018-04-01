<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Yaml\Tag;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Guilhem N. <egetick@gmail.com>
 */
final class TaggedValue
{
    private $tag;
    private $value;

    public function __construct(string $tag, $value)
    {
        $this->tag = $tag;
        $this->value = $value;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getValue()
    {
        return $this->value;
    }
}
