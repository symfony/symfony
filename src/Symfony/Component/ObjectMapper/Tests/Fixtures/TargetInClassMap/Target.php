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

/**
 * The target of the mapping under test, and also a source in the class map.
 */
class Target
{
    public string $label = 'untouched';
}
