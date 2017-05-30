<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Test;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\WebTestCase;

/**
 * @author Thomas Royer <thomas.royer.12@gmail.com>
 */
class DoctrineTestCase extends WebTestCase
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function setUp()
    {
        parent::setUp();

        $this->entityManager->beginTransaction();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->entityManager->rollback();
    }
}
