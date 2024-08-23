<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper;

use Symfony\Component\CallableWrapper\Attribute\CallableWrapperAttribute;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Json extends CallableWrapperAttribute implements CallableWrapperInterface
{
    public function wrap(\Closure $func): \Closure
    {
        return static function (mixed ...$args) use ($func): string {
            $result = $func(...$args);

            return json_encode($result, \JSON_THROW_ON_ERROR);
        };
    }
}
