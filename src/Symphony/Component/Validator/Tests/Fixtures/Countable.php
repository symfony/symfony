<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Fixtures;

class Countable implements \Countable
{
    private $content;

    public function __construct(array $content)
    {
        $this->content = $content;
    }

    public function count()
    {
        return count($this->content);
    }
}
