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
     * @param int[]  $thresholds    A hash associating groups to thresholds
     * @param string $regex         Will be matched against messages, to decide
     *                              whether to display a stack trace
     * @param bool[] $verboseOutput Keyed by groups
     */
    private function __construct(array $thresholds = [], $regex = '', $verboseOutput = [])
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
            $this->verboseOutput[$group] = (bool) $status;
        }
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
            if (!\in_array($key, ['max', 'disabled', 'verbose', 'quiet'], true)) {
                throw new \InvalidArgumentException(sprintf('Unknown configuration option "%s".', $key));
            }
        }

        if (isset($normalizedConfiguration['disabled'])) {
            return self::inDisabledMode();
        }

        $verboseOutput = [];
        if (!isset($normalizedConfiguration['verbose'])) {
            $normalizedConfiguration['verbose'] = true;
        }

        foreach (['unsilenced', 'direct', 'indirect', 'self', 'other'] as $group) {
            $verboseOutput[$group] = (bool) $normalizedConfiguration['verbose'];
        }

        if (isset($normalizedConfiguration['quiet']) && \is_array($normalizedConfiguration['quiet'])) {
            foreach ($normalizedConfiguration['quiet'] as $shushedGroup) {
                $verboseOutput[$shushedGroup] = false;
            }
        }

        return new self(
            isset($normalizedConfiguration['max']) ? $normalizedConfiguration['max'] : [],
            '',
            $verboseOutput
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
