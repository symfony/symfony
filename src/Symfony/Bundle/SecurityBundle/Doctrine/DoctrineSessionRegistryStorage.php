<?php

namespace Symfony\Bundle\SecurityBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\StaticPHPDriver;
use Symfony\Component\Security\Http\Session\SessionInformation;
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
        return $this->em->find('Security:DoctrineSessionInformation', $sessionId);
    }

    /**
     * Obtains the maintained information for one user.
     *
     * @param string $username The user identifier.
     * @param boolean $includeExpiredSessions.
     * @return array An array of SessionInformation objects.
     */
    public function getSessionInformations($username, $includeExpiredSessions = false)
    {
        $query = $this->em->createQuery(
            'SELECT si FROM Security:DoctrineSessionInformation si WHERE si.username = ?1'.($includeExpiredSessions ? '' : ' AND si.expired IS NULL').' ORDER BY si.lastRequest DESC'
        );
        $query->setParameter(1, $username);

        return $query->getResult();
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
