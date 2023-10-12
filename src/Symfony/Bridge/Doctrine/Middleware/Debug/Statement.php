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

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 */
final class Statement extends AbstractStatementMiddleware
{
    private Query $query;

    public function __construct(
        StatementInterface $statement,
        private DebugDataHolder $debugDataHolder,
        private string $connectionName,
        string $sql,
        private ?Stopwatch $stopwatch = null,
    ) {
        parent::__construct($statement);

        $this->query = new Query($sql);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->query->setValue($param, $value, $type);

        parent::bindValue($param, $value, $type);
    }

    public function execute(): ResultInterface
    {
        // clone to prevent variables by reference to change
        $this->debugDataHolder->addQuery($this->connectionName, $query = clone $this->query);

        $this->stopwatch?->start('doctrine', 'doctrine');
        $query->start();

        try {
            return parent::execute();
        } finally {
            $query->stop();
            $this->stopwatch?->stop('doctrine');
        }
    }
}
