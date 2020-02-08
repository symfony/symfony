<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Doctrine;

use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineSender as BridgeDoctrineSender;

trigger_deprecation('symfony/messenger', '5.1', 'The "%s" class is deprecated, use "%s" instead. The Doctrine transport has been moved to package "symfony/doctrine-messenger" and will not be included by default in 6.0. Run "composer require symfony/doctrine-messenger".', DoctrineSender::class, BridgeDoctrineSender::class);

class_exists(BridgeDoctrineSender::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, to be removed in 6.0. Use symfony/doctrine-messenger instead.
     */
    class DoctrineSender
    {
    }
}
