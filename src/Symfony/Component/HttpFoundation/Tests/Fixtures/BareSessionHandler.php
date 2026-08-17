<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Fixtures;

/**
 * A session handler that provides nothing more than \SessionHandlerInterface does.
 *
 * Mocking the interface directly is not an option: PHP 8.6 warns about any class
 * implementing it without create_sid() and validateId(), which the generated
 * doubles would not have. It does not implement \SessionIdInterface nor
 * \SessionUpdateTimestampHandlerInterface on purpose, as handlers are wrapped
 * differently depending on whether they do.
 */
abstract class BareSessionHandler implements \SessionHandlerInterface
{
    abstract public function create_sid(): string;

    abstract public function validateId(string $id): bool;
}
