<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

use Symfony\Component\Locale\Exception\NotImplementedException;
use Symfony\Component\Locale\Stub\DateFormat\MonthTransformer;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class FullTransformer
{
    private $quoteMatch = "'(?:[^']+|'')*'";
    private $implementedChars = 'MLydGQqhDEaHkKmsz';
    private $notImplementedChars = 'YuwWFgecSAZvVW';
    private $regExp;

    private $transformers;
    private $pattern;

    public function __construct($pattern)
    {
        $this->pattern = $pattern;

        $implementedCharsMatch = $this->buildCharsMatch($this->implementedChars);
        $notImplementedCharsMatch = $this->buildCharsMatch($this->notImplementedChars);
        $this->regExp = "/($this->quoteMatch|$implementedCharsMatch|$notImplementedCharsMatch)/";

        $this->transformers = array(
            'M' => new MonthTransformer(),
            'L' => new MonthTransformer(),
            'y' => new YearTransformer(),
            'd' => new DayTransformer(),
            'G' => new EraTransformer(),
            'q' => new QuarterTransformer(),
            'Q' => new QuarterTransformer(),
            'h' => new Hour1201Transformer(),
            'D' => new DayOfYearTransformer(),
            'E' => new DayOfWeekTransformer(),
            'a' => new AmPmTransformer(),
            'H' => new Hour2400Transformer(),
            'K' => new Hour1200Transformer(),
            'k' => new Hour2401Transformer(),
            'm' => new MinuteTransformer(),
            's' => new SecondTransformer(),
            'z' => new TimeZoneTransformer(),
        );
    }

    public function format(\DateTime $dateTime)
    {
        $that = $this;

        $formatted = preg_replace_callback($this->regExp, function($matches) use ($that, $dateTime) {
            return $that->formatReplace($matches[0], $dateTime);
        }, $this->pattern);

        return $formatted;
    }

    public function formatReplace($dateChars, $dateTime)
    {
        $length = strlen($dateChars);

        if ("'" === $dateChars[0]) {
            if (preg_match("/^'+$/", $dateChars)) {
                return str_replace("''", "'", $dateChars);
            }
            return str_replace("''", "'", substr($dateChars, 1, -1));
        }

        if (isset($this->transformers[$dateChars[0]])) {
            $transformer = $this->transformers[$dateChars[0]];
            return $transformer->format($dateTime, $length);
        } else {
            // handle unimplemented characters
            if (false !== strpos($this->notImplementedChars, $dateChars[0])) {
                throw new NotImplementedException(sprintf("Unimplemented date character '%s' in format '%s'", $dateChars[0], $this->pattern));
            }
        }
    }

    public function getReverseMatchingRegExp($pattern)
    {
        $reverseMatchingRegExp = preg_replace_callback($this->regExp, function($matches) {
            if (isset($this->transformers[$dateChars[0]])) {
                $transformer = $this->transformers[$dateChars[0]];
                return $transformer->getReverseMatchingRegExp($length);
            }
        }, $this->pattern);

        return $reverseMatchingRegExp;
    }

    public function parse($pattern, $value)
    {
        $reverseMatchingRegExp = $this->getReverseMatchingRegExp($pattern);

        $options = array();

        if (preg_match($reverseMatchingRegExp, $value, $matches)) {
            foreach ($this->transformers as $char => $transformer) {
                if (isset($matches[$char])) {
                    $options = array_merge($options, $transformer->extractDateOptions($char, strlen($matches[$char])));
                }
            }

            return $this->calculateUnixTimestamp($options);
        } else {
            throw new \InvalidArgumentException(sprintf("Failed to match value '%s' with pattern '%s'", $value, $pattern));
        }
    }

    protected function buildCharsMatch($specialChars)
    {
        $specialCharsArray = str_split($specialChars);

        $specialCharsMatch = implode('|', array_map(function($char) {
            return $char . '+';
        }, $specialCharsArray));

        return $specialCharsMatch;
    }
}
