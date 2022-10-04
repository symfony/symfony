<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Annotation\Groups;

class TypedPropertiesObject
{
    /**
     * @Groups({"foo"})
     */
    public string $unInitialized;

    /**
     * @Groups({"foo"})
     */
    public string $initialized = 'value';

    /**
     * @Groups({"bar"})
     */
    public string $initialized2 = 'value';
}
