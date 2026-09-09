<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Attribute;

use Symfony\Component\HttpClient\Recorder\RecorderMode;

/**
 * @example #[UseRecord]                                                   replays ClassName/methodName.har next to the test, throws on a miss
 * @example #[UseRecord(recordIfMissing: true)]                            same, but a miss makes the real request and records it
 * @example #[UseRecord(mode: RecorderMode::Record)]                       always makes real requests and rewrites the file
 * @example #[UseRecord('my_record.har')]                                  relative to the test file directory
 * @example #[UseRecord('@shared/my_record.har')]                          relative to the http-recorder-directory parameter
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class UseRecord
{
    public function __construct(
        public readonly ?string $record = null,
        public readonly ?RecorderMode $mode = null,
        public readonly bool $recordIfMissing = false,
    ) {
    }
}
