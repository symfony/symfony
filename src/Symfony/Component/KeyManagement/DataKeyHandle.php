<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * A data key kept usable for as long as the store that handed it out.
 *
 * {@see DataKey} wipes its plaintext as soon as the consumer returns, because a key generated for
 * a single payload must not leak into a second one. A handle is the opposite trade-off: a
 * {@see DataKeyStoreInterface} exists precisely to unwrap once and encrypt many payloads, so the
 * plaintext is retained until the handle is released or destroyed, and wiped there.
 *
 * Retaining it is a deliberate choice, not an oversight: the plaintext lives for the lifetime of
 * the store, which means the lifetime of a process. Implementations MUST NOT persist it, nor put
 * it in a cache shared between processes.
 *
 * The reference is what an envelope records in place of the wrapped key, so a handle always knows
 * which stored data key it came from. Its shape is opaque to callers.
 *
 * Retained is not the same as printable: the plaintext is not a field of this object, so no dump of
 * it can print the key, see {@see KeyMaterial}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class DataKeyHandle
{
    use KeyMaterial;

    /**
     * Takes the plaintext out of `$dataKey`, which is consumed in the process: the key moves here
     * instead of being shared, so the DataKey wipes what it held and the handle becomes the only
     * thing able to hand the key out, or to wipe it.
     */
    public function __construct(
        public readonly string $reference,
        DataKey $dataKey,
    ) {
        $this->keepMaterial($dataKey->use(self::claim(...)));
    }

    /**
     * Passes the plaintext data key to `$consumer` and keeps it available for the next call.
     *
     * @template T
     *
     * @param \Closure(string): T $consumer
     *
     * @return T
     *
     * @throws LogicException If the handle has already been released
     */
    public function use(\Closure $consumer): mixed
    {
        if (!$this->hasMaterial()) {
            throw new LogicException('The data key handle has already been released.');
        }

        return $consumer($this->material());
    }

    /**
     * Wipes the plaintext ahead of destruction, for a store that drops a scope it no longer serves.
     */
    public function release(): void
    {
        $this->wipeMaterial();
    }

    public function isReleased(): bool
    {
        return !$this->hasMaterial();
    }

    public function __destruct()
    {
        $this->release();
    }

    /**
     * Copies the plaintext into a buffer nothing else holds.
     *
     * PHP strings are reference-counted with copy-on-write, and `sodium_memzero()` leaves a buffer
     * alone for as long as another variable shares it. Handing the parameter straight back would
     * therefore leave the key sitting in the {@see DataKey}'s buffer, unwiped, until the engine
     * gets around to releasing it. Writing to an offset is what forces the copy, so each side ends
     * up owning what it is responsible for wiping.
     */
    private static function claim(#[\SensitiveParameter] string $plaintext): string
    {
        if ('' !== $plaintext) {
            $plaintext[0] = $plaintext[0];
        }

        return $plaintext;
    }
}
