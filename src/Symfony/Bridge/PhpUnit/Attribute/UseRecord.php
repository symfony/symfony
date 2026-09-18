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

/**
 * Replays the HTTP exchanges of a test from a HAR file, failing on a miss.
 *
 * The SYMFONY_HTTP_RECORDER env var changes that: "record" rewrites the files of the tests that run, "missing" replays them and records only what they do not contain yet, "passthrough" makes real requests without touching the files.
 *
 * Relative paths start from the directory of the test, or from the http-recorder-directory parameter of SymfonyExtension when it is set.
 *
 * @example #[UseRecord]                      ClassName/methodName.har, or ClassName/methodName@dataSetName.har with a data provider; Namespace/ClassName/methodName.har in the http-recorder-directory
 * @example #[UseRecord('my_record.har')]     relative to the directory of the test, or to the http-recorder-directory
 * @example #[UseRecord('/path/to/my.har')]   absolute
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class UseRecord
{
    public function __construct(
        public readonly ?string $record = null,
    ) {
    }
}
