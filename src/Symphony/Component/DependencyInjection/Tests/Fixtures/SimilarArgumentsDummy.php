<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Fixtures;

class SimilarArgumentsDummy
{
    public $class1;
    public $class2;

    public function __construct(CaseSensitiveClass $class1, $token, CaseSensitiveClass $class2)
    {
        $this->class1 = $class1;
        $this->class2 = $class2;
    }
}
