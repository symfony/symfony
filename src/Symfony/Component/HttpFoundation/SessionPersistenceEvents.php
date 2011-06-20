<?php

namespace Symfony\Component\HttpFoundation;

/**
 * SessionPersistenceEvents
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
class SessionPersistenceEvents
{
    /**
     * Event called when SessionStoragePersistenceInterface.open() is called
     *
     * @var string
     */
    const OPEN = 'session.persistence.open';

    /**
     * Event called when SessionStoragePersistenceInterface.close() is called
     *
     * @var string
     */
    const CLOSE = 'session.persistence.close';

    /**
     * Event called when SessionStoragePersistenceInterface.read() is called
     *
     * @var string
     */
    const READ = 'session.persistence.read';

    /**
     * Event called when SessionStoragePersistenceInterface.write() is called
     *
     * @var string
     */
    const WRITE = 'session.persistence.write';

    /**
     * Event called when SessionStoragePersistenceInterface.destroy() is called
     *
     * @var string
     */
    const DESTROY = 'session.persistence.destroy';

    /**
     * Event called when SessionStoragePersistenceInterface.gc() is called
     *
     * @var string
     */
    const GC = 'session.persistence.gc';
}
