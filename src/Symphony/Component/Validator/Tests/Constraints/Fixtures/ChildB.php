<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints\Fixtures;

use Symphony\Component\Validator\Constraints as Assert;

class ChildB
{
    /**
     * @Assert\Valid
     * @Assert\NotBlank
     */
    public $name;
    /**
     * @var ChildA
     * @Assert\Valid
     * @Assert\NotBlank
     */
    public $childA;
}
