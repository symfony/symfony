<?php

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Model\AuditableEntryInterface;
use Symfony\Component\Security\Acl\Model\EntryInterface;
use Symfony\Component\Security\Acl\Model\AuditLoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Base audit logger implementation
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class AuditLogger implements AuditLoggerInterface
{
    /**
     * Performs some checks if logging was requested
     *
     * @param Boolean $granted
     * @param EntryInterface $ace
     * @return void
     */
    public function logIfNeeded($granted, EntryInterface $ace)
    {
        if (!$ace instanceof AuditableEntryInterface) {
            return;
        }

        if ($granted && $ace->isAuditSuccess()) {
            $this->doLog($granted, $ace);
        } else if (!$granted && $ace->isAuditFailure()) {
            $this->doLog($granted, $ace);
        }
    }

    /**
     * This method is only called when logging is needed
     *
     * @param Boolean $granted
     * @param EntryInterface $ace
     * @return void
     */
    abstract protected function doLog($granted, EntryInterface $ace);
}