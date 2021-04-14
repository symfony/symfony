<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Reference;

/**
 * @author Andreas Braun <git@alcaeus.org>
 */
class Reference
{
    private $name;
    private $anchor;

    public function __construct(string $name, ?Anchor $anchor = null)
    {
        $this->name = $name;
        $this->anchor = $anchor;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->anchor ? $this->anchor->getValue() : null;
    }
}
