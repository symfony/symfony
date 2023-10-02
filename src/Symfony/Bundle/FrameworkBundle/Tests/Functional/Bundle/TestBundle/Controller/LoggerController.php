<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

final class LoggerController
{
    public function index(LoggerInterface $logger)
    {
        $logger->debug('test1_'.__CLASS__);
        $logger->debug('test2_'.__CLASS__);
        $logger->debug('test3_'.__CLASS__);

        return new Response();
    }
}
