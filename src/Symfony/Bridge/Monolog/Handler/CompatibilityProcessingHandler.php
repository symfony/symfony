<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Logger;
use Monolog\LogRecord;

if (Logger::API >= 3) {
    /**
     * The base class for compatibility between Monolog 3 LogRecord and Monolog 1/2 array records.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     *
     * @internal
     */
    trait CompatibilityProcessingHandler
    {
        abstract private function doWrite(array|LogRecord $record): void;

        protected function write(LogRecord $record): void
        {
            $this->doWrite($record);
        }
    }
} else {
    /**
     * The base class for compatibility between Monolog 3 LogRecord and Monolog 1/2 array records.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     *
     * @internal
     */
    trait CompatibilityProcessingHandler
    {
        abstract private function doWrite(array|LogRecord $record): void;

        protected function write(array $record): void
        {
            $this->doWrite($record);
        }
    }
}
