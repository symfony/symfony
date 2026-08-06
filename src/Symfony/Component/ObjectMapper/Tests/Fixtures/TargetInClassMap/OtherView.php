<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TargetInClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * The class map maps Target to this view. Its transform belongs to the Target to OtherView
 * direction only and must not be applied when mapping Source to Target.
 */
class OtherView
{
    #[Map(source: 'label', transform: 'strtoupper')]
    public string $label = 'x';
}
