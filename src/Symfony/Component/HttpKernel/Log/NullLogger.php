<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Log;

use Psr\Log\NullLogger as PsrNullLogger;

/**
 * NullLogger.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class NullLogger extends PsrNullLogger implements LoggerInterface
{
    /**
     * @api
     *
     * @deprecated since version 2.2, to be removed in 3.0. Use emergency() which is PSR-3 compatible.
     */
    public function emerg($message, array $context = array())
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.2 and will be removed in 3.0. You should use the new emergency() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
    }

    /**
     * @api
     *
     * @deprecated since version 2.2, to be removed in 3.0. Use critical() which is PSR-3 compatible.
     */
    public function crit($message, array $context = array())
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.2 and will be removed in 3.0. You should use the new critical() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
    }

    /**
     * @api
     *
     * @deprecated since version 2.2, to be removed in 3.0. Use error() which is PSR-3 compatible.
     */
    public function err($message, array $context = array())
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.2 and will be removed in 3.0. You should use the new error() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
    }

    /**
     * @api
     *
     * @deprecated since version 2.2, to be removed in 3.0. Use warning() which is PSR-3 compatible.
     */
    public function warn($message, array $context = array())
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.2 and will be removed in 3.0. You should use the new warning() method instead, which is PSR-3 compatible.', E_USER_DEPRECATED);
    }
}
