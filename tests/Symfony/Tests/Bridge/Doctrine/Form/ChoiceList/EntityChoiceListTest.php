<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Form\ChoiceList;

require_once __DIR__.'/../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';

use Symfony\Tests\Bridge\Doctrine\Form\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;

class EntityChoiceListTest extends DoctrineOrmTestCase
{
    const SINGLE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity';

    const COMPOSITE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Form\Fixtures\CompositeIdentEntity';

    private $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->createTestEntityManager();
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testChoicesMustBeManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // no persist here!

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name',
            null,
            array(
                $entity1,
                $entity2,
            )
        );

        // triggers loading -> exception
        $choiceList->getChoices();
    }
}