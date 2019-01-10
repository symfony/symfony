<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Comparator;

/**
 * DateCompare compiles date comparisons.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DateComparator extends Comparator
{
    const TIME_TYPE_MODIFIED = 'M';
    const TIME_TYPE_CHANGED = 'C';
    const TIME_TYPE_ACCESSED = 'A';

    private $timeType;

    public function __construct(string $test, string $timeType = self::TIME_TYPE_MODIFIED)
    {
        if (!preg_match('#^\s*(==|!=|[<>]=?|after|since|before|until)?\s*(.+?)\s*$#i', $test, $matches)) {
            throw new \InvalidArgumentException(sprintf('Don\'t understand "%s" as a date test.', $test));
        }

        try {
            $date = new \DateTime($matches[2]);
            $target = $date->format('U');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid date.', $matches[2]));
        }

        $operator = isset($matches[1]) ? $matches[1] : '==';
        if ('since' === $operator || 'after' === $operator) {
            $operator = '>';
        }

        if ('until' === $operator || 'before' === $operator) {
            $operator = '<';
        }

        $this->timeType = $timeType;
        $this->setOperator($operator);
        $this->setTarget($target);
    }

    public function getTimeType(): string
    {
        return $this->timeType;
    }
}
