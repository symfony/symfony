<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\InheritedTemplates;

use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;

/**
 * @template T
 */
class Envelope
{
    /** @var T */
    public mixed $data = null;

    /**
     * @param T $payload
     */
    public function __construct(
        public mixed $payload = null,
    ) {
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        return $this->data;
    }
}

/**
 * @extends Envelope<Dummy>
 */
class DummyEnvelope extends Envelope
{
}

/**
 * @template T
 * @template U
 *
 * @extends Envelope<U>
 */
class ChildEnvelope extends Envelope
{
}

/**
 * @template V
 *
 * @extends ChildEnvelope<int, list<V>>
 */
class GrandChildEnvelope extends ChildEnvelope
{
}

/**
 * @extends GrandChildEnvelope<Dummy>
 */
class DummyGrandChildEnvelope extends GrandChildEnvelope
{
}

/**
 * @template T
 */
trait HasItem
{
    /** @var T */
    public mixed $item = null;
}

class DummyItemUser
{
    /** @use HasItem<Dummy> */
    use HasItem;
}
