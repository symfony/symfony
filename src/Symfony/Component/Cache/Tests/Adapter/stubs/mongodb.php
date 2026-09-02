<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * The autoload attempts are gated on the extension being loaded: without it,
 * the classes of the mongodb/mongodb library cannot be loaded anyway, as some
 * of them implement interfaces provided by the extension.
 */

namespace MongoDB {
    if (!class_exists(Client::class, \extension_loaded('mongodb'))) {
        class Client
        {
            public function __construct(?string $uri = null, array $uriOptions = [], array $driverOptions = [])
            {
            }

            public function getCollection(string $databaseName, string $collectionName, array $options = [])
            {
                return new Collection();
            }

            public function getDatabase(string $databaseName, array $options = [])
            {
                return new Database();
            }
        }
    }

    if (!class_exists(Database::class, \extension_loaded('mongodb'))) {
        class Database
        {
            public function getCollection(string $collectionName, array $options = [])
            {
                return new Collection();
            }

            public function command($command, array $options = [])
            {
            }
        }
    }

    if (!class_exists(Collection::class, \extension_loaded('mongodb'))) {
        class Collection
        {
            public function bulkWrite(array $operations, array $options = [])
            {
            }

            public function countDocuments($filter = [], array $options = [])
            {
            }

            public function createIndex($key, array $options = [])
            {
            }

            public function deleteMany($filter, array $options = [])
            {
            }

            public function find($filter = [], array $options = [])
            {
            }

            public function findOne($filter = [], array $options = [])
            {
            }

            public function updateMany($filter, $update, array $options = [])
            {
            }
        }
    }
}

namespace MongoDB\BSON {
    if (!class_exists(Binary::class, \extension_loaded('mongodb'))) {
        class Binary
        {
            public const TYPE_GENERIC = 0;

            public function __construct(
                private string $data = '',
                private int $type = self::TYPE_GENERIC,
            ) {
            }

            public function getData(): string
            {
                return $this->data;
            }

            public function getType(): int
            {
                return $this->type;
            }
        }
    }

    if (!class_exists(UTCDateTime::class, \extension_loaded('mongodb'))) {
        class UTCDateTime
        {
            public readonly string $milliseconds;

            public function __construct(int|string|\DateTimeInterface|null $milliseconds = null)
            {
                if ($milliseconds instanceof \DateTimeInterface) {
                    $milliseconds = $milliseconds->format('Uv');
                }

                $this->milliseconds = (string) ($milliseconds ?? (new \DateTimeImmutable())->format('Uv'));
            }

            public function __toString(): string
            {
                return $this->milliseconds;
            }
        }
    }

    if (!class_exists(Regex::class, \extension_loaded('mongodb'))) {
        class Regex
        {
            public function __construct(
                private string $pattern,
                private string $flags = '',
            ) {
            }

            public function getPattern(): string
            {
                return $this->pattern;
            }

            public function getFlags(): string
            {
                return $this->flags;
            }

            public function __toString(): string
            {
                return \sprintf('/%s/%s', $this->pattern, $this->flags);
            }
        }
    }
}
