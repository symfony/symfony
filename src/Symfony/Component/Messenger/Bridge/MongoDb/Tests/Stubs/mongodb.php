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
        }
    }

    if (!class_exists(Collection::class, \extension_loaded('mongodb'))) {
        class Collection
        {
            public function countDocuments($filter = [], array $options = [])
            {
            }

            public function createIndex($key, array $options = [])
            {
            }

            public function deleteMany($filter, array $options = [])
            {
            }

            public function deleteOne($filter, array $options = [])
            {
            }

            public function find($filter = [], array $options = [])
            {
            }

            public function findOne($filter = [], array $options = [])
            {
            }

            public function findOneAndUpdate($filter, $update, array $options = [])
            {
            }

            public function insertOne($document, array $options = [])
            {
            }
        }
    }

    if (!class_exists(DeleteResult::class, \extension_loaded('mongodb'))) {
        class DeleteResult
        {
            public function getDeletedCount()
            {
            }
        }
    }

    if (!class_exists(InsertOneResult::class, \extension_loaded('mongodb'))) {
        class InsertOneResult
        {
            public function getInsertedId()
            {
            }
        }
    }
}

namespace MongoDB\BSON {
    if (!class_exists(ObjectId::class, \extension_loaded('mongodb'))) {
        class ObjectId
        {
            private string $oid;

            public function __construct(?string $id = null)
            {
                if (null !== $id && !preg_match('/^[0-9a-f]{24}$/i', $id)) {
                    throw new \InvalidArgumentException(\sprintf('Error parsing ObjectId string: "%s"', $id));
                }

                $this->oid = $id ?? bin2hex(random_bytes(12));
            }

            public function __toString(): string
            {
                return $this->oid;
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

            public function toDateTime(): \DateTime
            {
                return \DateTime::createFromFormat('U.v', \sprintf('%d.%03d', intdiv((int) $this->milliseconds, 1000), (int) $this->milliseconds % 1000));
            }

            public function __toString(): string
            {
                return $this->milliseconds;
            }
        }
    }
}

namespace MongoDB\Driver {
    if (!class_exists(WriteConcern::class, \extension_loaded('mongodb'))) {
        class WriteConcern
        {
            public const MAJORITY = 'majority';

            public function __construct(
                public string|int $w,
                ?int $wtimeout = null,
                ?bool $journal = null,
            ) {
            }
        }
    }

    if (!class_exists(Session::class, \extension_loaded('mongodb'))) {
        class Session
        {
            public function isInTransaction(): bool
            {
                return false;
            }
        }
    }

    if (!interface_exists(CursorInterface::class, \extension_loaded('mongodb'))) {
        interface CursorInterface extends \Traversable
        {
        }
    }
}

namespace MongoDB\Driver\Exception {
    if (!interface_exists(Exception::class, \extension_loaded('mongodb'))) {
        interface Exception extends \Throwable
        {
        }
    }

    if (!class_exists(RuntimeException::class, \extension_loaded('mongodb'))) {
        class RuntimeException extends \RuntimeException implements Exception
        {
        }
    }
}

namespace MongoDB\Model {
    if (!class_exists(BSONDocument::class, \extension_loaded('mongodb'))) {
        #[\AllowDynamicProperties]
        class BSONDocument extends \ArrayObject
        {
            public function __construct(array $input = [], int $flags = \ArrayObject::ARRAY_AS_PROPS, string $iteratorClass = \ArrayIterator::class)
            {
                parent::__construct($input, $flags, $iteratorClass);
            }
        }
    }
}

namespace MongoDB\Operation {
    if (!class_exists(FindOneAndUpdate::class, \extension_loaded('mongodb'))) {
        class FindOneAndUpdate
        {
            public const RETURN_DOCUMENT_AFTER = 2;
        }
    }
}
