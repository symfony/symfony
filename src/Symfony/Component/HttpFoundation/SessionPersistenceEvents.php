<?php

namespace Symfony\Component\HttpFoundation;

class SessionPersistenceEvents
{
    /**
     * @var string
     */
    const OPEN = 'session.persistence.open';

    /**
     * @var string
     */
    const CLOSE = 'session.persistence.close';

    /**
     * @var string
     */
    const READ = 'session.persistence.read';

    /**
     * @var string
     */
    const WRITE = 'session.persistence.write';

    /**
     * @var string
     */
    const DESTROY = 'session.persistence.destroy';

    /**
     * @var string
     */
    const GC = 'session.persistence.gc';
}
