<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures\InheritedTemplates;

use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;

/**
 * @template T
 */
class Envelope
{
    /** @var T */
    public mixed $data = null;
}

/**
 * @extends Envelope<Dummy>
 */
class DummyEnvelope extends Envelope
{
}

/**
 * @template T
 * @template U of Dummy
 *
 * @extends Envelope<U>
 */
class ChildEnvelope extends Envelope
{
}

/**
 * @template V of Dummy
 *
 * @template-extends ChildEnvelope<int, V>
 */
class GrandChildEnvelope extends ChildEnvelope
{
}

/**
 * @phpstan-extends GrandChildEnvelope<Dummy>
 */
class DummyGrandChildEnvelope extends GrandChildEnvelope
{
}

/**
 * @template V
 *
 * @extends Envelope<list<V>>
 */
class ListEnvelope extends Envelope
{
}

/**
 * @template T
 */
class SameNameEnvelope extends Envelope
{
}

/**
 * @extends SameNameEnvelope<int>
 */
class IntSameNameEnvelope extends SameNameEnvelope
{
}

/**
 * @extends Envelope<int, string>
 */
class TooManyTypesEnvelope extends Envelope
{
}

/**
 * @extends Envelope<int>
 *
 * @phpstan-extends Envelope<string>
 */
class PhpStanPrefixedEnvelope extends Envelope
{
}

/**
 * @template K
 * @template V
 */
class Pair
{
}

/**
 * @template A
 * @template B
 *
 * @extends Pair<B, A>
 */
class SwappedPair extends Pair
{
}

/**
 * @template T
 *
 * @extends Pair<int, Pair<T, string>>
 */
class NestedPair extends Pair
{
}

/**
 * @extends Pair<int>
 */
class MissingTypePair extends Pair
{
}

/**
 * @template T
 */
interface Holder
{
}

/**
 * @template U
 *
 * @implements Holder<U>
 */
abstract class AbstractHolder implements Holder
{
}

/**
 * @extends AbstractHolder<Dummy>
 */
class DummyHolder extends AbstractHolder
{
}

/**
 * @template T
 */
class UntaggedHolder implements Holder
{
}

/**
 * @extends UntaggedHolder<Dummy>
 */
class DummyUntaggedHolder extends UntaggedHolder
{
}

/**
 * @template X
 *
 * @extends Holder<list<X>>
 */
interface ListHolder extends Holder
{
}

/**
 * @implements ListHolder<int>
 */
class IntListHolder implements ListHolder
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

/**
 * @template U
 */
class ListItemUser
{
    /**
     * @use HasItem<list<U>>
     */
    use HasItem;
}

/**
 * @extends ListItemUser<int>
 */
class IntListItemUser extends ListItemUser
{
}
