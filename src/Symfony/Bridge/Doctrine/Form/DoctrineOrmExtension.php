<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form;

use Symfony\Component\Form\AbstractExtension;
use Doctrine\ORM\EntityManager;

class DoctrineOrmExtension extends AbstractExtension
{
    /**
     * The Doctrine 2 entity manager
     * @var Doctrine\ORM\EntityManager
     */
    protected $em = null;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    protected function loadTypes()
    {
        return array(
            new Type\EntityType($this->em),
        );
    }

    protected function loadTypeGuesser()
    {
        return new DoctrineOrmTypeGuesser($this->em);
    }
}
