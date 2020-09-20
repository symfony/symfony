<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

class StringableTag
{
    /**
     * @var string
     */
    private $tag;

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function __toString(): string
    {
        return $this->tag;
    }
}
