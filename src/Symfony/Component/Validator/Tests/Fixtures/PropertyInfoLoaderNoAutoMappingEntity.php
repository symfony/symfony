<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Assert\DisableAutoMapping
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoLoaderNoAutoMappingEntity
{
    public $string;

    /**
     * @Assert\EnableAutoMapping
     */
    public $autoMappingExplicitlyEnabled;
}
