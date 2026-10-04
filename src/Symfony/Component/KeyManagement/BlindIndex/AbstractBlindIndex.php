<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex;

use Symfony\Component\KeyManagement\BlindIndexInterface;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * What the two blind indexes of the component share, which is everything but {@see open()}.
 *
 * It is not an extension point: a key the component cannot reach is served by a
 * {@see DataKeyStoreInterface} of one's own, two methods handing out a {@see DataKeyHandle}, which
 * is how a key held in plaintext or kept somewhere this component knows nothing about reaches an
 * index. Nothing {@see open()} could do is out of reach that way.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
abstract class AbstractBlindIndex implements BlindIndexInterface
{
    /**
     * Tells this derivation from any other use of the same data key, and leaves room for a second.
     */
    private const string TAG_INFO = 'symfony/key-management/blind-index/v1/';

    /**
     * Width of the subkey handed to the algorithm, which {@see AlgorithmInterface::tag()} states.
     *
     * Not {@see AlgorithmInterface::TAG_BYTES}: what goes in and what comes out happen to be the
     * same width here, and nothing says they have to stay that way.
     */
    private const int KEY_BYTES = 32;

    private readonly AlgorithmInterface $algorithm;

    /**
     * The index key, held for as long as it stays usable.
     *
     * A handle a store hands out is the one the store holds, and not this object's to keep:
     * `forget()`, which a long-running worker calls between two units of work, releases it. Hence
     * reopened when released rather than once.
     */
    private ?DataKeyHandle $handle = null;

    /**
     * @param string $name Names this index among those over the same data key, and keys its derivation
     *
     * @throws InvalidArgumentException If the name is empty
     */
    public function __construct(
        private readonly string $name,
        private readonly ProjectionInterface|CoveringProjectionInterface $projection,
        ?AlgorithmInterface $algorithm = null,
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('A blind index must be named, since the name is what separates its tags from those of every other index over the same data key.');
        }

        $this->algorithm = $algorithm ?? new HmacSha256();
    }

    final public function of(#[\SensitiveParameter] string $value): string
    {
        if ($this->projection instanceof CoveringProjectionInterface) {
            throw new LogicException(\sprintf('The projection "%s" covers several forms of a value, which "%s::of()" cannot answer with one tag: read them with "allOf()".', get_debug_type($this->projection), static::class));
        }

        return $this->allOf($value)[0];
    }

    /**
     * The tags are derived under a subkey of the data key, and not under the data key itself.
     *
     * A store opens one key for every index over it, which is the point of
     * {@see \Symfony\Component\KeyManagement\StoredKeyBlindIndex}, so two indexes commonly hold the
     * same plaintext. Keyed by it directly, they would hand out the same tag for the same value:
     * anyone reading the two columns would learn which rows of one hold a value of the other, which
     * is a correlation neither column was meant to allow. The name makes the subkeys differ, and
     * separates them from anything else encrypting under that key.
     *
     * The subkey is derived per call rather than kept: holding it would leave usable key material
     * outside the handle, which exists so that `forget()` wipes it. Two HMACs over 32 bytes is also
     * what the derivation costs, which is the tag's own order of magnitude. One subkey serves every
     * form of a value, an index having one name, so it is derived once for them all.
     *
     * @return list<string>
     */
    final public function allOf(#[\SensitiveParameter] string $value): array
    {
        $forms = $this->projection instanceof CoveringProjectionInterface
            ? $this->projection->cover($value)
            : [$this->projection->project($value)];

        if (null === $this->handle || $this->handle->isReleased()) {
            $this->handle = $this->open();
        }

        $tags = $this->handle->use(function (#[\SensitiveParameter] string $key) use ($forms): array {
            $subkey = hash_hkdf('sha256', $key, self::KEY_BYTES, self::TAG_INFO.$this->name);

            return array_map(fn (string $form): string => $this->algorithm->tag($form, $subkey), $forms);
        });

        foreach ($tags as $tag) {
            if (AlgorithmInterface::TAG_BYTES !== \strlen($tag)) {
                throw new LogicException(\sprintf('The blind index algorithm "%s" returned a %d-byte tag instead of %d.', get_debug_type($this->algorithm), \strlen($tag), AlgorithmInterface::TAG_BYTES));
            }
        }

        return array_values(array_map('bin2hex', $tags));
    }

    /**
     * Opens the index key, once and then again whenever the handle it returned was released.
     */
    abstract protected function open(): DataKeyHandle;
}
