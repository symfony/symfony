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

class SessionEvents
{
    /**
     * @var string
     */
    const START = 'session.start';

    /**
     * @var string
     */
    const READ = 'session.read';

    /**
     * @var string
     */
    const REMOVE = 'session.remove';

    /**
     * @var string
     */
    const WRITE = 'session.write';

    /**
     * @var string
     */
    const PRE_REGENERATE = 'session.regenerate.pre';

    /**
     * @var string
     */
    const POST_REGENERATE = 'session.regenerate.post';
}
