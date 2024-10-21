<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\Fixtures\Controller;

final readonly class Task
{
    public function __construct(
        public int $id,
        public string $description,
    ) {
    }
}
