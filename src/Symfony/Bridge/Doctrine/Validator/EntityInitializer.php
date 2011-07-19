<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Doctrine\ORM\Proxy\Proxy;

/**
 * Automatically loads proxy object before validation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EntityInitializer implements ObjectInitializerInterface
{
    protected $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function initialize($object)
    {
        if ($object instanceof Proxy) {
            $this->registry->getEntityManagerForClass(get_class($object))->getUnitOfWork()->initializeObject($object);
        }
    }
}
