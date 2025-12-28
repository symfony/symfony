<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * Fixture class for testing property name conflicts with method names.
 */
class PropertyNameConflictDummy
{
    public bool $boolean = false;

    public string $string = 'string';

    public int $unchanged = 42;

    public function setValue(): void
    {
        $this->boolean = true;
    }

    public function setString(): void
    {
        $this->string = 'changed';
    }
}
