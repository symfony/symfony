<?php

namespace Symfony\Component\Security\Acl\Model;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Interface for audit loggers
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface AuditLoggerInterface
{
    /**
     * This method is called whenever access is granted, or denied, and
     * administrative mode is turned off.
     *
     * @param Boolean $granted
     * @param EntryInterface $ace
     * @return void
     */
    function logIfNeeded($granted, EntryInterface $ace);
}