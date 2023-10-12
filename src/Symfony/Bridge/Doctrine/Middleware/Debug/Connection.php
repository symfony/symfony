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
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $connection,
        private DebugDataHolder $debugDataHolder,
        private ?Stopwatch $stopwatch,
        private string $connectionName,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new Statement(
            parent::prepare($sql),
            $this->debugDataHolder,
            $this->connectionName,
            $sql,
            $this->stopwatch,
        );
    }

    public function query(string $sql): Result
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            return parent::query($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function exec(string $sql): int
    {
        $this->debugDataHolder->addQuery($this->connectionName, $query = new Query($sql));

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            $affectedRows = parent::exec($sql);
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }

        return $affectedRows;
    }

    public function beginTransaction(): void
    {
        $query = new Query('"START TRANSACTION"');
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::beginTransaction();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function commit(): void
    {
        $query = new Query('"COMMIT"');
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::commit();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }

    public function rollBack(): void
    {
        $query = new Query('"ROLLBACK"');
        $this->debugDataHolder->addQuery($this->connectionName, $query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            parent::rollBack();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
}
