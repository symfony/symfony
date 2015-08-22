<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;

/**
 * @author Premi Giorgio <giosh94mhz@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group legacy
 */
class UnloadedEntityChoiceListSingleAssociationToIntIdWithQueryBuilderTest extends UnloadedEntityChoiceListSingleAssociationToIntIdTest
{
    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        $qb = $this->em->createQueryBuilder()->select('s')->from($this->getEntityClass(), 's');
        $loader = new ORMQueryBuilderLoader($qb);

        return new EntityChoiceList($this->em, $this->getEntityClass(), null, $loader);
    }
}
