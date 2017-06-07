<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Formatter;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

/**
 * Formats incoming records for console output by coloring them depending on log level.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class ConsoleFormatter extends LineFormatter
{
    const SIMPLE_FORMAT = "%start_tag%[%datetime%] %channel%.%level_name%:%end_tag% %message% %context% %extra%\n";

    const INFO = 'info';
    const ERROR = 'error';
    const NOTICE = 'comment';
    const DEBUG  = null;

    /**
     * @var array
     */
    private $formatLevelMap = array(
        Logger::EMERGENCY => self::ERROR,
        Logger::ALERT => self::ERROR,
        Logger::CRITICAL => self::ERROR,
        Logger::ERROR => self::ERROR,
        Logger::WARNING => self::NOTICE,
        Logger::NOTICE => self::NOTICE,
        Logger::INFO => self::INFO,
        Logger::DEBUG => self::DEBUG,
    );

    /**
     * {@inheritdoc}
     */
    public function __construct(
        $format = null,
        $dateFormat = null,
        $allowInlineLineBreaks = false,
        $ignoreEmptyContextAndExtra = true,
        Array $formatLevelMap = array()
    ) {
        parent::__construct($format, $dateFormat, $allowInlineLineBreaks, $ignoreEmptyContextAndExtra);

        $this->formatLevelMap = $formatLevelMap + $this->formatLevelMap;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        if ($this->formatLevelMap[$record['level']] && !empty($this->formatLevelMap[$record['level']])) {
            $record['start_tag'] = '<'.$this->formatLevelMap[$record['level']].'>';
            $record['end_tag'] = '</'.$this->formatLevelMap[$record['level']].'>';
        } else {
            $record['start_tag'] = '';
            $record['end_tag'] = '';
        }

        return parent::format($record);
    }
}
