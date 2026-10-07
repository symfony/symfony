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

class OtherCallableRule implements CallableRuleInterface
{
    public static function getTagAttributes(): array
    {
        return ['key' => 'other callable'];
    }

    public function evaluate(int $value): bool
    {
        return $value < 0;
    }
}
