<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass;

class ClosureTagRule implements ClosureTagInterface
{
    public static function getKey(): string
    {
        return 'closure';
    }

    public function evaluate(int $value): bool
    {
        return $value > 0;
    }
}
