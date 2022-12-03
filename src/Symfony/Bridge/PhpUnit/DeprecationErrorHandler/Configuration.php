<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

/**
 * @internal
 */
class Configuration
{
    /**
     * @var int[]
     */
    private $thresholds;

    /**
     * @var string
     */
    private $regex;

    /**
     * @var bool
     */
    private $enabled = true;

    /**
     * @var bool[]
     */
    private $verboseOutput;

    /**
     * @var string[]
     */
    private $ignoreDeprecationPatterns = [];

    /**
     * @var bool
     */
    private $generateBaseline = false;

    /**
     * @var string
     */
    private $baselineFile = '';

    /**
     * @var array
     */
    private $baselineDeprecations = [];

    /**
     * @var string|null
     */
    private $logFile = null;

    /**
     * @param int[]       $thresholds       A hash associating groups to thresholds
     * @param string      $regex            Will be matched against messages, to decide whether to display a stack trace
     * @param bool[]      $verboseOutput    Keyed by groups
     * @param string      $ignoreFile       The path to the ignore deprecation patterns file
     * @param bool        $generateBaseline Whether to generate or update the baseline file
     * @param string      $baselineFile     The path to the baseline file
     * @param string|null $logFile          The path to the log file
     */
    private function __construct(array $thresholds = [], $regex = '', $verboseOutput = [], $ignoreFile = '', $generateBaseline = false, $baselineFile = '', $logFile = null)
    {
        $groups = ['total', 'indirect', 'direct', 'self'];

        foreach ($thresholds as $group => $threshold) {
            if (!\in_array($group, $groups, true)) {
                throw new \InvalidArgumentException(sprintf('Unrecognized threshold "%s", expected one of "%s".', $group, implode('", "', $groups)));
            }
            if (!is_numeric($threshold)) {
                throw new \InvalidArgumentException(sprintf('Threshold for group "%s" has invalid value "%s".', $group, $threshold));
            }
            $this->thresholds[$group] = (int) $threshold;
        }
        if (isset($this->thresholds['direct'])) {
            $this->thresholds += [
                'self' => $this->thresholds['direct'],
            ];
        }
        if (isset($this->thresholds['indirect'])) {
            $this->thresholds += [
                'direct' => $this->thresholds['indirect'],
                'self' => $this->thresholds['indirect'],
            ];
        }
        foreach ($groups as $group) {
            if (!isset($this->thresholds[$group])) {
                $this->thresholds[$group] = 999999;
            }
        }
        $this->regex = $regex;

        $this->verboseOutput = [
            'unsilenced' => true,
            'direct' => true,
            'indirect' => true,
            'self' => true,
            'other' => true,
        ];

        foreach ($verboseOutput as $group => $status) {
            if (!isset($this->verboseOutput[$group])) {
                throw new \InvalidArgumentException(sprintf('Unsupported verbosity group "%s", expected one of "%s".', $group, implode('", "', array_keys($this->verboseOutput))));
            }
            $this->verboseOutput[$group] = $status;
        }

        if ($ignoreFile) {
            if (!is_file($ignoreFile)) {
                throw new \InvalidArgumentException(sprintf('The ignoreFile "%s" does not exist.', $ignoreFile));
            }
            set_error_handler(static function ($t, $m) use ($ignoreFile, &$line) {
                throw new \RuntimeException(sprintf('Invalid pattern found in "%s" on line "%d"', $ignoreFile, 1 + $line).substr($m, 12));
            });
            try {
                foreach (file($ignoreFile) as $line => $pattern) {
                    if ('#' !== (trim($pattern)[0] ?? '#')) {
                        preg_match($pattern, '');
                        $this->ignoreDeprecationPatterns[] = $pattern;
                    }
                }
            } finally {
                restore_error_handler();
            }
        }

        if ($generateBaseline && !$baselineFile) {
            throw new \InvalidArgumentException('You cannot use the "generateBaseline" configuration option without providing a "baselineFile" configuration option.');
        }
        $this->generateBaseline = $generateBaseline;
        $this->baselineFile = $baselineFile;
        if ($this->baselineFile && !$this->generateBaseline) {
            if (is_file($this->baselineFile)) {
                $map = json_decode(file_get_contents($this->baselineFile));
                foreach ($map as $baseline_deprecation) {
                    $this->baselineDeprecations[$baseline_deprecation->location][$baseline_deprecation->message] = $baseline_deprecation->count;
                }
            } else {
                throw new \InvalidArgumentException(sprintf('The baselineFile "%s" does not exist.', $this->baselineFile));
            }
        }

        $this->logFile = $logFile;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param DeprecationGroup[] $deprecationGroups
     *
     * @return bool
     */
    public function tolerates(array $deprecationGroups)
    {
        $grandTotal = 0;

        foreach ($deprecationGroups as $name => $group) {
            if ('legacy' !== $name) {
                $grandTotal += $group->count();
            }
        }

        if ($grandTotal > $this->thresholds['total']) {
            return false;
        }

        foreach (['self', 'direct', 'indirect'] as $deprecationType) {
            if ($deprecationGroups[$deprecationType]->count() > $this->thresholds[$deprecationType]) {
                return false;
            }
        }

        return true;
    }

    public function isIgnoredDeprecation(Deprecation $deprecation): bool
    {
        if (!$this->ignoreDeprecationPatterns) {
            return false;
        }
        $result = @preg_filter($this->ignoreDeprecationPatterns, '$0', $deprecation->getMessage());
        if (\PREG_NO_ERROR !== preg_last_error()) {
            throw new \RuntimeException(preg_last_error_msg());
        }

        return (bool) $result;
    }

    /**
     * @return bool
     */
    public function isBaselineDeprecation(Deprecation $deprecation)
    {
        if ($deprecation->isLegacy()) {
            return false;
        }

        if ($deprecation->originatesFromAnObject()) {
            $location = $deprecation->originatingClass().'::'.$deprecation->originatingMethod();
        } else {
            $location = 'procedural code';
        }

        $message = $deprecation->getMessage();
        $result = isset($this->baselineDeprecations[$location][$message]) && $this->baselineDeprecations[$location][$message] > 0;
        if ($this->generateBaseline) {
            if ($result) {
                ++$this->baselineDeprecations[$location][$message];
            } else {
                $this->baselineDeprecations[$location][$message] = 1;
                $result = true;
            }
        } elseif ($result) {
            --$this->baselineDeprecations[$location][$message];
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isGeneratingBaseline()
    {
        return $this->generateBaseline;
    }

    public function getBaselineFile()
    {
        return $this->baselineFile;
    }

    public function writeBaseline()
    {
        $map = [];
        foreach ($this->baselineDeprecations as $location => $messages) {
            foreach ($messages as $message => $count) {
                $map[] = [
                    'location' => $location,
                    'message' => $message,
                    'count' => $count,
                ];
            }
        }
        file_put_contents($this->baselineFile, json_encode($map, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $message
     *
     * @return bool
     */
    public function shouldDisplayStackTrace($message)
    {
        return '' !== $this->regex && preg_match($this->regex, $message);
    }

    /**
     * @return bool
     */
    public function isInRegexMode()
    {
        return '' !== $this->regex;
    }

    /**
     * @return bool
     */
    public function verboseOutput($group)
    {
        return $this->verboseOutput[$group];
    }

    public function shouldWriteToLogFile()
    {
        return null !== $this->logFile;
    }

    public function getLogFile()
    {
        return $this->logFile;
    }

    /**
     * @param string $serializedConfiguration an encoded string, for instance
     *                                        max[total]=1234&max[indirect]=42
     *
     * @return self
     */
    public static function fromUrlEncodedString($serializedConfiguration)
    {
        parse_str($serializedConfiguration, $normalizedConfiguration);
        foreach (array_keys($normalizedConfiguration) as $key) {
            if (!\in_array($key, ['max', 'disabled', 'verbose', 'quiet', 'ignoreFile', 'generateBaseline', 'baselineFile', 'logFile'], true)) {
                throw new \InvalidArgumentException(sprintf('Unknown configuration option "%s".', $key));
            }
        }

        $normalizedConfiguration += [
            'max' => ['total' => 0],
            'disabled' => false,
            'verbose' => true,
            'quiet' => [],
            'ignoreFile' => '',
            'generateBaseline' => false,
            'baselineFile' => '',
            'logFile' => null,
        ];

        if ('' === $normalizedConfiguration['disabled'] || filter_var($normalizedConfiguration['disabled'], \FILTER_VALIDATE_BOOLEAN)) {
            return self::inDisabledMode();
        }

        $verboseOutput = [];
        foreach (['unsilenced', 'direct', 'indirect', 'self', 'other'] as $group) {
            $verboseOutput[$group] = filter_var($normalizedConfiguration['verbose'], \FILTER_VALIDATE_BOOLEAN);
        }

        if (\is_array($normalizedConfiguration['quiet'])) {
            foreach ($normalizedConfiguration['quiet'] as $shushedGroup) {
                $verboseOutput[$shushedGroup] = false;
            }
        }

        return new self(
            $normalizedConfiguration['max'],
            '',
            $verboseOutput,
            $normalizedConfiguration['ignoreFile'],
            filter_var($normalizedConfiguration['generateBaseline'], \FILTER_VALIDATE_BOOLEAN),
            $normalizedConfiguration['baselineFile'],
            $normalizedConfiguration['logFile']
        );
    }

    /**
     * @return self
     */
    public static function inDisabledMode()
    {
        $configuration = new self();
        $configuration->enabled = false;

        return $configuration;
    }

    /**
     * @return self
     */
    public static function inStrictMode()
    {
        return new self(['total' => 0]);
    }

    /**
     * @return self
     */
    public static function inWeakMode()
    {
        $verboseOutput = [];
        foreach (['unsilenced', 'direct', 'indirect', 'self', 'other'] as $group) {
            $verboseOutput[$group] = false;
        }

        return new self([], '', $verboseOutput);
    }

    /**
     * @return self
     */
    public static function fromNumber($upperBound)
    {
        return new self(['total' => $upperBound]);
    }

    /**
     * @return self
     */
    public static function fromRegex($regex)
    {
        return new self([], $regex);
    }
}
