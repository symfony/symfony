<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

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
    trait CompatibilityProcessor
    {
        abstract private function doInvoke(array|LogRecord $record): array|LogRecord;

        public function __invoke(LogRecord $record): LogRecord
        {
            return $this->doInvoke($record);
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
    trait CompatibilityProcessor
    {
        abstract private function doInvoke(array|LogRecord $record): array|LogRecord;

        public function __invoke(array $record): array
        {
            return $this->doInvoke($record);
        }
    }
}
