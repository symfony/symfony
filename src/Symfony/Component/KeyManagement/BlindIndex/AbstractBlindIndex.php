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
    private readonly AlgorithmInterface $algorithm;

    /**
     * The index key, held for as long as it stays usable.
     *
     * A handle a store hands out is the one the store holds, and not this object's to keep:
     * `forget()`, which a long-running worker calls between two units of work, releases it. Hence
     * reopened when released rather than once.
     */
    private ?DataKeyHandle $handle = null;

    public function __construct(
        private readonly ProjectionInterface $projection,
        ?AlgorithmInterface $algorithm = null,
    ) {
        $this->algorithm = $algorithm ?? new HmacSha256();
    }

    final public function of(#[\SensitiveParameter] string $value): string
    {
        $value = $this->projection->project($value);

        if (null === $this->handle || $this->handle->isReleased()) {
            $this->handle = $this->open();
        }

        return bin2hex($this->handle->use(fn (#[\SensitiveParameter] string $key): string => $this->algorithm->tag($value, $key)));
    }

    /**
     * Opens the index key, once and then again whenever the handle it returned was released.
     */
    abstract protected function open(): DataKeyHandle;
}
