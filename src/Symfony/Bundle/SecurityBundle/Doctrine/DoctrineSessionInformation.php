<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Security\Http\Session\SessionInformation;

/**
 * DoctrineSessionInformation.
 *
 * Allows to persist SessionInformation using Doctrine.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class DoctrineSessionInformation extends SessionInformation
{
    public static function loadMetadata(ClassMetadata $metadata)
    {
        $metadata->setTableName('session_information');

        $metadata->mapField(array(
            'id' => true,
            'fieldName' => 'sessionId',
            'columnName' => 'session_id',
            'type' => 'string'
        ));

        $metadata->mapField(array(
            'fieldName' => 'username',
            'type' => 'string'
        ));

        $metadata->mapField(array(
            'fieldName' => 'expired',
            'type' => 'datetime',
            'nullable' => true
        ));

        $metadata->mapField(array(
            'fieldName' => 'lastRequest',
            'columnName' => 'last_request',
            'type' => 'datetime',
            'nullable' => true
        ));
    }
}
