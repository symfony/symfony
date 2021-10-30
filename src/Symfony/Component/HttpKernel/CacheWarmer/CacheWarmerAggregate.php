<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 * Aggregates several cache warmers into a single one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class CacheWarmerAggregate implements CacheWarmerInterface
{
    private $warmers;
    private $debug;
    private $deprecationLogsFilepath;
    private $optionalsEnabled = false;
    private $onlyOptionalsEnabled = false;

    public function __construct(iterable $warmers = [], bool $debug = false, string $deprecationLogsFilepath = null)
    {
        $this->warmers = $warmers;
        $this->debug = $debug;
        $this->deprecationLogsFilepath = $deprecationLogsFilepath;
    }

    public function enableOptionalWarmers()
    {
        $this->optionalsEnabled = true;
    }

    public function enableOnlyOptionalWarmers()
    {
        $this->onlyOptionalsEnabled = $this->optionalsEnabled = true;
    }

    /**
     * Warms up the cache.
     *
     * @return string[] A list of classes or files to preload on PHP 7.4+
     */
    public function warmUp(string $cacheDir)
    {
        if ($collectDeprecations = $this->debug && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
            $collectedLogs = [];
            $previousHandler = set_error_handler(function ($type, $message, $file, $line) use (&$collectedLogs, &$previousHandler) {
                if (\E_USER_DEPRECATED !== $type && \E_DEPRECATED !== $type) {
                    return $previousHandler ? $previousHandler($type, $message, $file, $line) : false;
                }

                if (isset($collectedLogs[$message])) {
                    ++$collectedLogs[$message]['count'];

                    return null;
                }

                $backtrace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 3);
                // Clean the trace by removing first frames added by the error handler itself.
                for ($i = 0; isset($backtrace[$i]); ++$i) {
                    if (isset($backtrace[$i]['file'], $backtrace[$i]['line']) && $backtrace[$i]['line'] === $line && $backtrace[$i]['file'] === $file) {
                        $backtrace = \array_slice($backtrace, 1 + $i);
                        break;
                    }
                }

                $collectedLogs[$message] = [
                    'type' => $type,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                    'trace' => $backtrace,
                    'count' => 1,
                ];

                return null;
            });
        }

        $preload = [];
        try {
            foreach ($this->warmers as $warmer) {
                if (!$this->optionalsEnabled && $warmer->isOptional()) {
                    continue;
                }
                if ($this->onlyOptionalsEnabled && !$warmer->isOptional()) {
                    continue;
                }

                $preload[] = array_values((array) $warmer->warmUp($cacheDir));
            }
        } finally {
            if ($collectDeprecations) {
                restore_error_handler();

                if (is_file($this->deprecationLogsFilepath)) {
                    $previousLogs = unserialize(file_get_contents($this->deprecationLogsFilepath));
                    if (\is_array($previousLogs)) {
                        $collectedLogs = array_merge($previousLogs, $collectedLogs);
                    }
                }

                file_put_contents($this->deprecationLogsFilepath, serialize(array_values($collectedLogs)));
            }
        }

        return array_values(array_unique(array_merge([], ...$preload)));
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return bool always false
     */
    public function isOptional(): bool
    {
        return false;
    }
}
