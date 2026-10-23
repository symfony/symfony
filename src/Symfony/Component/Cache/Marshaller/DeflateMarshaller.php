<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Exception\CacheException;

/**
 * Compresses values using gzdeflate().
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DeflateMarshaller implements MarshallerInterface
{
    /**
     * @param MarshallerInterface $marshaller Nesting DeflateMarshaller decorators is deprecated since Symfony 8.3
     */
    public function __construct(
        private MarshallerInterface $marshaller,
    ) {
        if (!\function_exists('gzdeflate')) {
            throw new CacheException('The "zlib" PHP extension is not loaded.');
        }

        if ($marshaller instanceof self) {
            trigger_deprecation('symfony/cache', '8.3', 'Nesting "%s" decorators is deprecated. Use a single decorator instead.', self::class);
        }
    }

    public function marshall(array $values, ?array &$failed): array
    {
        $values = $this->marshaller->marshall($values, $failed);

        foreach ($values as $id => $value) {
            $compressed = 32 > \strlen($value) ? null : gzdeflate($value);

            if (null !== $compressed && \strlen($compressed) < \strlen($value)) {
                $values[$id] = $compressed;
                continue;
            }

            // Keep possible DEFLATE streams compressed so unmarshall() cannot inflate raw payloads.
            $inflated = 128 < \strlen($value) ? @inflate_add(inflate_init(\ZLIB_ENCODING_RAW), substr($value, 0, 128), \ZLIB_NO_FLUSH) : @gzinflate($value);
            if (false !== $inflated) {
                $values[$id] = $compressed ?? gzdeflate($value);
            }
        }

        return $values;
    }

    public function unmarshall(string $value): mixed
    {
        if (false !== $inflatedValue = @gzinflate($value)) {
            $value = $inflatedValue;
        }

        return $this->marshaller->unmarshall($value);
    }
}
