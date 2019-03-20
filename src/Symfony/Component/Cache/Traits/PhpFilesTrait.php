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
use Symfony\Component\VarExporter\VarExporter;

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

    private $includeHandler;
    private $appendOnly;
    private $values = [];
    private $files = [];

    private static $startTime;

    public static function isSupported()
    {
        self::$startTime = self::$startTime ?? $_SERVER['REQUEST_TIME'] ?? time();

        return \function_exists('opcache_invalidate') && ('cli' !== \PHP_SAPI || filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN)) && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
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
                try {
                    list($expiresAt) = include $file;
                } catch (\ErrorException $e) {
                    $expiresAt = $time;
                }

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
            $missingIds = [];
        } else {
            $now = time();
            $missingIds = $ids;
            $ids = [];
        }
        $values = [];

        begin:
        foreach ($ids as $id) {
            if (null === $value = $this->values[$id] ?? null) {
                $missingIds[] = $id;
            } elseif ('N;' === $value) {
                $values[$id] = null;
            } elseif ($value instanceof \Closure) {
                $values[$id] = $value();
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
                } catch (\ErrorException $e) {
                    unset($missingIds[$k]);
                }
            }
        } finally {
            restore_error_handler();
        }

        $ids = $missingIds;
        $missingIds = [];
        goto begin;
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        if ($this->appendOnly && isset($this->values[$id])) {
            return true;
        }

        set_error_handler($this->includeHandler);
        try {
            $file = $this->files[$id] ?? $this->files[$id] = $this->getFile($id);
            list($expiresAt, $value) = include $file;
        } catch (\ErrorException $e) {
            return false;
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
            $isStaticValue = true;
            if (null === $value) {
                $value = "'N;'";
            } elseif (\is_object($value) || \is_array($value)) {
                try {
                    $value = VarExporter::export($value, $isStaticValue);
                } catch (\Exception $e) {
                    throw new InvalidArgumentException(sprintf('Cache key "%s" has non-serializable %s value.', $key, \is_object($value) ? \get_class($value) : 'array'), 0, $e);
                }
            } elseif (\is_string($value)) {
                // Wrap "N;" in a closure to not confuse it with an encoded `null`
                if ('N;' === $value) {
                    $isStaticValue = false;
                }
                $value = var_export($value, true);
            } elseif (!\is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Cache key "%s" has non-serializable %s value.', $key, \gettype($value)));
            } else {
                $value = var_export($value, true);
            }

            if (!$isStaticValue) {
                $value = str_replace("\n", "\n    ", $value);
                $value = "static function () {\n\n    return {$value};\n\n}";
            }

            $file = $this->files[$key] = $this->getFile($key, true);
            // Since OPcache only compiles files older than the script execution start, set the file's mtime in the past
            $ok = $this->write($file, "<?php return [{$expiry}, {$value}];\n", self::$startTime - 10) && $ok;

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
        $this->values = [];

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
