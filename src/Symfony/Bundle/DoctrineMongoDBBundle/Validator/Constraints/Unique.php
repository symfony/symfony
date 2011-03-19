<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Doctrine MongoDB ODM unique value constraint.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class Unique extends Constraint
{
    public $message = 'The value for {{ property }} already exists.';
    public $path;
    public $documentManager;

    public function getDefaultOption()
    {
        return 'path';
    }

    public function getRequiredOptions()
    {
        return array('path');
    }

    public function validatedBy()
    {
        return 'doctrine_odm.mongodb.unique';
    }

    public function getTargets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }

    public function getDocumentManagerId()
    {
        $id = 'doctrine.odm.mongodb.document_manager';
        if (null !== $this->documentManager) {
            $id = sprintf('doctrine.odm.mongodb.%s_document_manager', $this->documentManager);
        }

        return $id;
    }
}
