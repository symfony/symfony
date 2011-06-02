<?php

namespace Symfony\Bundle\SecurityBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\StaticPHPDriver;
use Symfony\Component\Security\Http\Session\SessionInformation;
use Symfony\Component\Security\Http\Session\SessionInformationIterator;
use Symfony\Component\Security\Http\Session\SessionRegistryStorageInterface;

class DoctrineSessionRegistryStorage implements SessionRegistryStorageInterface
{
    protected $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;

        $driver = new StaticPHPDriver(__DIR__);
        $this->em->getConfiguration()->setMetadataDriverImpl($driver);
    }

    /**
     * not implemented
     */
    public function getUsers()
    {
        throw new \BadMethodCallException("Not implemented.");
    }

    /**
     * Obtains the maintained information for one session.
     *
     * @param string $sessionId the session identifier key.
     * @return DoctrineSessionInformation a SessionInformation object.
     */
    public function getSessionInformation($sessionId)
    {
        return $this->em->find('Symfony\Bundle\SecurityBundle\Doctrine\DoctrineSessionInformation', $sessionId);
    }

    /**
     * Obtains the maintained information for one user.
     *
     * @param string $sessionId the session identifier key.
     * @return SessionInformationIterator a SessionInformationIterator object.
     */
    public function getSessionInformations($username, $includeExpiredSessions)
    {
        $sessions = new SessionInformationIterator();

        foreach ($this->em->getRepository('Symfony\Bundle\SecurityBundle\Doctrine\DoctrineSessionInformation')->findBy(array('username' => $username)) as $sessionInformation) {
            $sessions->add($sessionInformation);
        }

        return $sessions;
    }

    /**
     * Adds information for one session.
     *
     * @param string $sessionId the session identifier key.
     * @param DoctrineSessionInformation a SessionInformation object.
     * @return void
     */
    public function setSessionInformation(SessionInformation $sessionInformation)
    {
        $this->em->persist($sessionInformation);
        $this->em->flush();
    }

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     * @return void
     */
    public function removeSessionInformation($sessionId)
    {
        if (null !== $sessionInformation = $this->getSessionInformation($sessionId)) {
            $this->em->remove($sessionInformation);
            $this->em->flush();
        }
    }
}
