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

/**
 * @author Valentin Udaltsov <udaltsov.valentin@gmail.com>
 */
final class Php74Dummy
{
    public string $uninitializedProperty;

    public string $initializedProperty = 'defaultValue';
}
