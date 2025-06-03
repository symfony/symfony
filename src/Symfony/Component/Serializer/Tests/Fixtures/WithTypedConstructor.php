<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

final class WithTypedConstructor
{
    /**
     * @var string
     */
    public $string;
    /**
     * @var bool
     */
    public $bool;
    /**
     * @var int
     */
    public $int;

    public function __construct(string $string, bool $bool, int $int)
    {
        $this->string = $string;
        $this->bool = $bool;
        $this->int = $int;
    }
}
