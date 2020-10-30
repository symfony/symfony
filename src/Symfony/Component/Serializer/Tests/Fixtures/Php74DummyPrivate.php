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
 * @author Alexander Borisov <boshurik@gmail.com>
 */
final class Php74DummyPrivate
{
    private string $uninitializedProperty;

    private string $initializedProperty = 'defaultValue';

    public function getUninitializedProperty(): string
    {
        return $this->uninitializedProperty;
    }

    public function getInitializedProperty(): string
    {
        return $this->initializedProperty;
    }
}
