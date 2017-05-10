<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Doctrine\ORM\EntityManager;

/**
 * Use a Doctrine entity for persisting session state
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Eric Clemmons <eric@smarterspam.com>
 * @author Michael Williams <michael.williams@funsational.com>
 */
class DoctrineSessionStorage extends NativeSessionStorage
{
    private $em;
    private $entityOptions;

    /**
     * Constructor.
     *
     * @param \Doctrine\ORM\EntityManager   $em             EntityManger instance
     * @param array                         $options        An associative array of session options
     * @param string                        $entityClass    Entity class for visitor persistance
     *
     * @throws \InvalidArgumentException When "class" option is not provided
     *
     * @see NativeSessionStorage::__construct()
     */
    public function __construct(EntityManager $em, array $options = array(), $entityOptions = array())
    {
        if (!array_key_exists('class', $entityOptions)) {
            throw new \InvalidArgumentException('You must provide the "class" option for a DoctrineSessionStorage.');
        }
        
        $this->em = $em;
        
        $this->entityOptions = array_merge(array(
            'id'    =>  'sessionId',
            'data'  =>  'sessionData',
            'time'  =>  'sessionTime',
        ), $entityOptions);
        
        parent::__construct($options);
    }

    /**
     * Starts the session.
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        // use this object as the session handler
        session_set_save_handler(
            array($this, 'sessionOpen'),
            array($this, 'sessionClose'),
            array($this, 'sessionRead'),
            array($this, 'sessionWrite'),
            array($this, 'sessionDestroy'),
            array($this, 'sessionGC')
        );

        parent::start();
    }

    /**
     * Opens a session.
     *
     * @param  string $path  (ignored)
     * @param  string $name  (ignored)
     *
     * @return Boolean true, if the session was opened, otherwise an exception is thrown
     */
    public function sessionOpen($path = null, $name = null)
    {
        return true;
    }

    /**
     * Closes a session.
     *
     * @return Boolean true, if the session was closed, otherwise false
     */
    public function sessionClose()
    {
        // do nothing
        return true;
    }

    /**
     * Destroys a session.
     *
     * @param  string $sessionId  A session ID
     *
     * @return Boolean   true, if the session was destroyed
     */
    public function sessionDestroy($sessionId)
    {
        if (! $entity = $this->getEntity($sessionId)) {
            throw new \InvalidArgumentException(sprintf('Cannot find session ID %s for %s', $sessionId, $this->getEntityClass()));
        }

        $this->getEntityManager()->remove($entity);

        return true;
    }

    /**
     * Cleans up old sessions.
     *
     * @param  int $lifetime  The lifetime of a session
     *
     * @return Boolean true, if old sessions have been cleaned
     */
    public function sessionGC($lifetime)
    {
        $stmt = sprintf(
            'DELETE FROM %s s WHERE s.%s < (:time - %s)',
            $this->entityOptions['class'],
            $this->entityOptions['time'],
            $lifetime
        );

        $query = $this->getEntityManager()->createQuery($stmt)
                                          ->setParameter('time', time());
        $query->getResult();

        return true;
    }

    /**
     * Reads a session.
     *
     * @param  string $sessionId A session ID
     *
     * @return string      The session data if the session was read or created, otherwise an exception is thrown
     */
    public function sessionRead($sessionId)
    {
        if ($entity = $this->getEntity($sessionId)) {
            $getter = 'get'.ucfirst($this->entityOptions['data']);
            
            return $entity->{$getter}();
        } else {
            $this->createNewSession($sessionId);
        }
    }

    /**
     * Writes session data.
     *
     * @param  string $sessionId    A session ID
     * @param  string $data         A serialized chunk of session data
     *
     * @return Boolean true, if the session was written
     */
    public function sessionWrite($sessionId, $data)
    {
        if ($entity = $this->getEntity($sessionId)) {
            $this->updateEntity($entity, array(
                'id'    => $sessionId,
                'data'  => $data,
                'time'  => time(),
            ));
        } else {
            $this->createNewSession($sessionId, $data);
        }
        
        return true;
    }

    /**
     * Creates a new session with the given $sessionId and $data
     *
     * @param string $sessionId
     * @param string $data
     */
    private function createNewSession($sessionId, $data = '')
    {
        $class  = $this->entityOptions['class'];
        $entity = new $class;

        $this->updateEntity($entity, array(
            'id'    => $sessionId,
            'data'  => $data,
            'time'  => time(),
        ));

        return true;
    }

    /**
     * Retrieve the supplied entity manager
     */
    private function getEntityManager()
    {
        return $this->em;
    }

    /**
     * Find the session entity using the session_id
     *
     * @var string $sessionId
     */
    private function getEntity($sessionId)
    {
        $repo = $this->getEntityManager()->getRepository($this->entityOptions['class']);
        
        return $repo->findOneBy(array($this->entityOptions['id'] => $sessionId));
    }

    private function updateEntity($entity, $values)
    {
        foreach ($values as $field => $value) {
            $setter = 'set'.ucfirst($this->entityOptions[$field]);
            $entity->{$setter}($value);
        }

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        
        return true;
    }

}