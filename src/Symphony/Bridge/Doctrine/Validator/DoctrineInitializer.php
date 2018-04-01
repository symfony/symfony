<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Validator;

use Doctrine\Common\Persistence\ManagerRegistry;
use Symphony\Component\Validator\ObjectInitializerInterface;

/**
 * Automatically loads proxy object before validation.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class DoctrineInitializer implements ObjectInitializerInterface
{
    protected $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function initialize($object)
    {
        $manager = $this->registry->getManagerForClass(get_class($object));
        if (null !== $manager) {
            $manager->initializeObject($object);
        }
    }
}
