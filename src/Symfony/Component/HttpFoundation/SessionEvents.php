<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * SessionEvents
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
class SessionEvents
{
    /**
     * Event called on SessionStorageInterface.start()
     *
     * @var string
     */
    const START = 'session.start';

    /**
     * Event called on SessionStorageInterface.read()
     *
     * @var string
     */
    const READ = 'session.read';

    /**
     * Event called on SessionStorageInterface.remove()
     *
     * @var string
     */
    const REMOVE = 'session.remove';

    /**
     * Event called on SessionStorageInterface.write()
     *
     * @var string
     */
    const WRITE = 'session.write';

    /**
     * Event called on before regenerating a new session id in SessionStorageInterface.regenerate()
     *
     * @var string
     */
    const PRE_REGENERATE = 'session.regenerate.pre';

    /**
     * Event called on after regenerating a new session id in SessionStorageInterface.regenerate()
     *
     * @var string
     */
    const POST_REGENERATE = 'session.regenerate.post';
}
