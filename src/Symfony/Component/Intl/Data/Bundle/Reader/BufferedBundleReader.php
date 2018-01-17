<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Reader;

use Symfony\Component\Intl\Data\Util\RingBuffer;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class BufferedBundleReader implements BundleReaderInterface
{
    private $reader;
    private $buffer;

    /**
     * Buffers a given reader.
     *
     * @param BundleReaderInterface $reader     The reader to buffer
     * @param int                   $bufferSize The number of entries to store in the buffer
     */
    public function __construct(BundleReaderInterface $reader, int $bufferSize)
    {
        $this->reader = $reader;
        $this->buffer = new RingBuffer($bufferSize);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        $hash = $path.'//'.$locale;

        if (!isset($this->buffer[$hash])) {
            $this->buffer[$hash] = $this->reader->read($path, $locale);
        }

        return $this->buffer[$hash];
    }
}
