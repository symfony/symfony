<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\PhpMarshaller;

/**
 * @author Piotr Stankowski <git@trakos.pl>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 *
 * @internal
 */
trait PhpFilesTrait
{
    use FilesystemCommonTrait {
        doClear as private doCommonClear;
        doDelete as private doCommonDelete;
    }

    private $marshaller;
    private $includeHandler;
    private $appendOnly;
    private $values = array();
    private $files = array();

    private static $startTime;

    public static function isSupported()
    {
        self::$startTime = self::$startTime ?? $_SERVER['REQUEST_TIME'] ?? time();

        return \function_exists('opcache_invalidate') && ini_get('opcache.enable') && ('cli' !== \PHP_SAPI || ini_get('opcache.enable_cli'));
    }

    /**
     * @return bool
     */
    public function prune()
    {
        $time = time();
        $pruned = true;

        set_error_handler($this->includeHandler);
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
                list($expiresAt) = include $file;

                if ($time >= $expiresAt) {
                    $pruned = $this->doUnlink($file) && !file_exists($file) && $pruned;
                }
            }
        } finally {
            restore_error_handler();
        }

        return $pruned;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        if ($this->appendOnly) {
            $now = 0;
            $missingIds = array();
        } else {
            $now = time();
            $missingIds = $ids;
            $ids = array();
        }
        $values = array();

        begin:
        foreach ($ids as $id) {
            if (null === $value = $this->values[$id] ?? null) {
                $missingIds[] = $id;
            } elseif ('N;' === $value) {
                $values[$id] = null;
            } elseif ($value instanceof \Closure) {
                $values[$id] = $value();
            } elseif (\is_string($value) && isset($value[2]) && ':' === $value[1]) {
                $values[$id] = ($this->marshaller ?? $this->marshaller = new DefaultMarshaller())->unmarshall($value);
            } else {
                $values[$id] = $value;
            }
            if (!$this->appendOnly) {
                unset($this->values[$id]);
            }
        }

        if (!$missingIds) {
            return $values;
        }

        set_error_handler($this->includeHandler);
        try {
            foreach ($missingIds as $k => $id) {
                try {
                    $file = $this->files[$id] ?? $this->files[$id] = $this->getFile($id);
                    list($expiresAt, $this->values[$id]) = include $file;
                    if ($now >= $expiresAt) {
                        unset($this->values[$id], $missingIds[$k]);
                    }
                } catch (\Exception $e) {
                    unset($missingIds[$k]);
                }
            }
        } finally {
            restore_error_handler();
        }

        $ids = $missingIds;
        $missingIds = array();
        goto begin;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        if ($this->appendOnly && $this->values[$id]) {
            return true;
        }

        set_error_handler($this->includeHandler);
        try {
            $file = $this->files[$id] ?? $this->files[$id] = $this->getFile($id);
            list($expiresAt, $value) = include $file;
        } finally {
            restore_error_handler();
        }
        if ($this->appendOnly) {
            $now = 0;
            $this->values[$id] = $value;
        } else {
            $now = time();
        }

        return $now < $expiresAt;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        $ok = true;
        $expiry = $lifetime ? time() + $lifetime : 'PHP_INT_MAX';
        $allowCompile = self::isSupported();

        foreach ($values as $key => $value) {
            unset($this->values[$key]);
            $objectsCount = 0;
            if (null === $value) {
                $value = 'N;';
            } elseif (\is_object($value) || \is_array($value)) {
                try {
                    $e = null;
                    $serialized = serialize($value);
                } catch (\Exception $e) {
                }
                if (null !== $e || false === $serialized) {
                    throw new InvalidArgumentException(sprintf('Cache key "%s" has non-serializable %s value.', $key, \is_object($value) ? get_class($value) : 'array'), 0, $e);
                }
                // Keep value serialized if it contains any internal references
                $value = false !== strpos($serialized, ';R:') ? $serialized : PhpMarshaller::marshall($value, $objectsCount);
            } elseif (\is_string($value)) {
                // Wrap strings if they could be confused with serialized objects or arrays
                if ('N;' === $value || (isset($value[2]) && ':' === $value[1])) {
                    ++$objectsCount;
                }
            } elseif (!\is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Cache key "%s" has non-serializable %s value.', $key, gettype($value)));
            }

            $value = var_export($value, true);
            if ($objectsCount) {
                $value = PhpMarshaller::optimize($value);
                $value = "static function () {\n\nreturn {$value};\n\n}";
            }

            $file = $this->files[$key] = $this->getFile($key, true);
            // Since OPcache only compiles files older than the script execution start, set the file's mtime in the past
            $ok = $this->write($file, "<?php return array({$expiry}, {$value});\n", self::$startTime - 10) && $ok;

            if ($allowCompile) {
                @opcache_invalidate($file, true);
                @opcache_compile_file($file);
            }
        }

        if (!$ok && !is_writable($this->directory)) {
            throw new CacheException(sprintf('Cache directory is not writable (%s)', $this->directory));
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear($namespace)
    {
        $this->values = array();

        return $this->doCommonClear($namespace);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        foreach ($ids as $id) {
            unset($this->values[$id]);
        }

        return $this->doCommonDelete($ids);
    }

    protected function doUnlink($file)
    {
        if (self::isSupported()) {
            @opcache_invalidate($file, true);
        }

        return @unlink($file);
    }
}
