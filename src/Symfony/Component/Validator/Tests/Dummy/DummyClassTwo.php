<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Dummy;

use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Expression(expression: '1 + 1 = 2')]
class DummyClassTwo
{
    /**
     * @var string|null
     */
    #[Assert\NotBlank]
    public $code;

    /**
     * @var string|null
     */
    #[Assert\Email]
    public $email;

    /**
     * @var DummyClassOne|null
     */
    #[Assert\DisableAutoMapping]
    public $dummyClassOne;
}
