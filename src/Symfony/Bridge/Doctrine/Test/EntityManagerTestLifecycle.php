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

/**
 * Enclose single test into transaction
 *
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class EntityManagerTestLifecycle
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function setUp()
    {
        $this->em->beginTransaction();
    }

    public function tearDown()
    {
        $this->em->rollback();
    }
}
