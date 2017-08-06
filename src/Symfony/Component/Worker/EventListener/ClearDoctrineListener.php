<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\EventListener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Worker\Consumer\ConsumerEvents;
use Symfony\Component\Worker\Loop\LoopEvents;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ClearDoctrineListener implements EventSubscriberInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return array(
            LoopEvents::SLEEP => 'clearDoctrine',
            ConsumerEvents::POST_CONSUME => 'clearDoctrine',
        );
    }

    public function clearDoctrine()
    {
        if ($this->entityManager) {
            $this->entityManager->clear();
        }
    }
}
