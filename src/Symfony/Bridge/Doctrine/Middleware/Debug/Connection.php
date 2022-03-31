<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
final class Connection extends AbstractConnectionMiddleware
{
    private $nestingLevel = 0;
    private $debugDataHolder;
    private $stopwatch;
    private $connectionName;

    public function __construct(ConnectionInterface $connection, DebugDataHolder $debugDataHolder, ?Stopwatch $stopwatch, string $connectionName)
    {
        parent::__construct($connection);

        $this->debugDataHolder = $debugDataHolder;
        $this->stopwatch = $stopwatch;
        $this->connectionName = $connectionName;
    }

    public function prepare(string $sql): DriverStatement
    {
        return new Statement(
            parent::prepare($sql),
            $this->debugDataHolder,
            $this->connectionName,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        $query->start();

        try {
            $result = parent::query($sql);
        } finally {
            $query->stop();

            if (null !== $this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }

        return $result;
    }

    public function exec(string $sql): int
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        $query->start();

        try {
            $affectedRows = parent::exec($sql);
        } finally {
            $query->stop();

            if (null !== $this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }

        return $affectedRows;
    }

    public function beginTransaction(): bool
    {
        $query = null;
        if (1 === ++$this->nestingLevel) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"START TRANSACTION"'));
        }

        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        if (null !== $query) {
            $query->start();
        }

        try {
            $ret = parent::beginTransaction();
        } finally {
            if (null !== $query) {
                $query->stop();
            }

            if (null !== $this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }

        return $ret;
    }

    public function commit(): bool
    {
        $query = null;
        if (1 === $this->nestingLevel--) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"COMMIT"'));
        }

        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        if (null !== $query) {
            $query->start();
        }

        try {
            $ret = parent::commit();
        } finally {
            if (null !== $query) {
                $query->stop();
            }

            if (null !== $this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }

        return $ret;
    }

    public function rollBack(): bool
    {
        $query = null;
        if (1 === $this->nestingLevel--) {
            $this->debugDataHolder->addQuery($this->connectionName, $query = new Query('"ROLLBACK"'));
        }

        if (null !== $this->stopwatch) {
            $this->stopwatch->start('doctrine', 'doctrine');
        }

        if (null !== $query) {
            $query->start();
        }

        try {
            $ret = parent::rollBack();
        } finally {
            if (null !== $query) {
                $query->stop();
            }

            if (null !== $this->stopwatch) {
                $this->stopwatch->stop('doctrine');
            }
        }

        return $ret;
    }
}
