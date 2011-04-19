<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Bundle\DoctrineMongoDBBundle\Logger\DoctrineMongoDBLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Data collector for the Doctrine MongoDB ODM.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class DoctrineMongoDBDataCollector extends DataCollector
{
    protected $logger;

    public function __construct(DoctrineMongoDBLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data['nb_queries'] = $this->logger->getNbQueries();
        $this->data['queries'] = $this->logger->getQueries();
    }

    public function getQueryCount()
    {
        return $this->data['nb_queries'];
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mongodb';
    }
}
