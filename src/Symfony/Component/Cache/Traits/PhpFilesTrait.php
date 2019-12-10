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
    private static $valuesCache = [];

    public static function isSupported()
    {
        self::$startTime = self::$startTime ?? $_SERVER['REQUEST_TIME'] ?? time();

        return \function_exists('opcache_invalidate') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN) && (!\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) || filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return bool
     */
    public function prune()
    {
        $time = time();
        $pruned = true;
        $getExpiry = true;

        set_error_handler($this->includeHandler);
        try {
            foreach ($this->scanHashDir($this->directory) as $file) {
                try {
                    if (\is_array($expiresAt = include $file)) {
                        $expiresAt = $expiresAt[0];
                    }
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
        $getExpiry = false;

        foreach ($ids as $id) {
            if (null === $value = $this->values[$id] ?? null) {
                $missingIds[] = $id;
            } elseif ('N;' === $value) {
                $values[$id] = null;
            } elseif (!\is_object($value)) {
                $values[$id] = $value;
            } elseif (!$value instanceof LazyValue) {
                $values[$id] = $value();
            } elseif (false === $values[$id] = include $value->file) {
                unset($values[$id], $this->values[$id]);
                $missingIds[] = $id;
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
            $getExpiry = true;

            foreach ($missingIds as $k => $id) {
                try {
                    $file = $this->files[$id] ?? $this->files[$id] = $this->getFile($id);

                    if (isset(self::$valuesCache[$file])) {
                        [$expiresAt, $this->values[$id]] = self::$valuesCache[$file];
                    } elseif (\is_array($expiresAt = include $file)) {
                        if ($this->appendOnly) {
                            self::$valuesCache[$file] = $expiresAt;
                        }

                        [$expiresAt, $this->values[$id]] = $expiresAt;
                    } elseif ($now < $expiresAt) {
                        $this->values[$id] = new LazyValue($file);
                    }

                    if ($now >= $expiresAt) {
                        unset($this->values[$id], $missingIds[$k], self::$valuesCache[$file]);
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
            $getExpiry = true;

            if (isset(self::$valuesCache[$file])) {
                [$expiresAt, $value] = self::$valuesCache[$file];
            } elseif (\is_array($expiresAt = include $file)) {
                if ($this->appendOnly) {
                    self::$valuesCache[$file] = $expiresAt;
                }

                [$expiresAt, $value] = $expiresAt;
            } elseif ($this->appendOnly) {
                $value = new LazyValue($file);
            }
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
            } elseif (!is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Cache key "%s" has non-serializable %s value.', $key, \gettype($value)));
            } else {
                $value = var_export($value, true);
            }

            $encodedKey = rawurlencode($key);

            if ($isStaticValue) {
                $value = "return [{$expiry}, {$value}];";
            } elseif ($this->appendOnly) {
                $value = "return [{$expiry}, static function () { return {$value}; }];";
            } else {
                // We cannot use a closure here because of https://bugs.php.net/76982
                $value = str_replace('\Symfony\Component\VarExporter\Internal\\', '', $value);
                $value = "namespace Symfony\Component\VarExporter\Internal;\n\nreturn \$getExpiry ? {$expiry} : {$value};";
            }

            $file = $this->files[$key] = $this->getFile($key, true);
            // Since OPcache only compiles files older than the script execution start, set the file's mtime in the past
            $ok = $this->write($file, "<?php //{$encodedKey}\n\n{$value}\n", self::$startTime - 10) && $ok;

            if ($allowCompile) {
                @opcache_invalidate($file, true);
                @opcache_compile_file($file);
            }
            unset(self::$valuesCache[$file]);
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
        unset(self::$valuesCache[$file]);

        if (self::isSupported()) {
            @opcache_invalidate($file, true);
        }

        return @unlink($file);
    }

    private function getFileKey(string $file): string
    {
        if (!$h = @fopen($file, 'rb')) {
            return '';
        }

        $encodedKey = substr(fgets($h), 8);
        fclose($h);

        return rawurldecode(rtrim($encodedKey));
    }
}

/**
 * @internal
 */
class LazyValue
{
    public $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }
}
