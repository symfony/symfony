<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Exception;

use Symfony\Component\Mailer\Transport\Dsn;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
class UnsupportedSchemeException extends LogicException
{
    public function __construct(Dsn $dsn)
    {
        parent::__construct(sprintf('The "%s" scheme is not supported for mailer "%s".', $dsn->getScheme(), $dsn->getHost()));
    }
}
