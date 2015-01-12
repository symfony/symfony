<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a date string and a DateInterval object
 *
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
class DateIntervalToStringTransformer implements DataTransformerInterface
{
    /**
     * Format used for generating strings
     * @var string
     */
    private $format;

    /**
     * Whether to parse by as a signed interval
     *
     * @var bool
     */
    private $parseSigned;

    /**
     * Transforms a \DateInterval instance to a string
     *
     * @see \DateInterval::format() for supported formats
     * @param  string                  $format      The date format
     * @param  bool                    $parseSigned Whether to parse by as a signed interval
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct($format = 'P%yY%mM%dDT%hH%iM%sS', $parseSigned = false)
    {
	$this->format = $format;
    }

    /**
     * Transforms a DateInterval object into a date string with the configured format
     * and timezone
     *
     * @param  \DateInterval                 $value A DateInterval object
     * @return string                        An ISO 8601 or relative date string like date interval presentation
     * @throws TransformationFailedException If the given value is not a \DateInterval
     *                                             instance..
     */
    public function transform($value)
    {
	if (null === $value) {
	    return '';
	}
	if (!$value instanceof \DateInterval) {
	    throw new TransformationFailedException('Expected a \DateInterval.');
	}

	return $value->format($this->format);
    }

    /**
     * Transforms a date string in the configured into a DateInterval object.
     *
     * @param  string                        $value An ISO 8601 or date string like date interval presentation
     * @return \DateInterval                 An instance of \DateInterval
     * @throws TransformationFailedException If the given value is not a string or
     *                                             if the date interval could not be parsed.
     */
    public function reverseTransform($value)
    {
	if (empty($value)) {
	    return;
	}
	if (!is_string($value)) {
	    throw new TransformationFailedException('Expected a string.');
	}
	try {
	    if ($this->isISO8601($value)) {
		$valuePattern = '/^'.preg_replace('/%([yYmMdDhHiIsSwW])(\w)/', '(?P<$1>\d+)$2', $this->format).'$/';
		if (!preg_match($valuePattern, $value)) {
		    throw new TransformationFailedException(
			sprintf('Value "%s" contains intervals not accepted by format "%s".', $value, $this->format)
		    );
		}
		$dateInterval = new \DateInterval($value);
	    } else {
		throw new TransformationFailedException('Non ISO 8601 date strings are not supported yet');
	    }
	} catch (TransformationFailedException $e) {
	    throw $e;
	} catch (\Exception $e) {
	    throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
	}

	return $dateInterval;
    }

    /**
     * @param $string
     * @return int
     */
    private function isISO8601($string)
    {
	return preg_match(
	    '/^P(?=\w*(?:\d|%\w))(?:\d+Y|%[yY]Y)?(?:\d+M|%[mM]M)?(?:(?:\d+D|%[dD]D)|(?:\d+W|%[wW]W))?(?:T(?:\d+H|[hH]H)?(?:\d+M|[iI]M)?(?:\d+S|[sS]S)?)?$/',
	    $string
	);
    }
}
