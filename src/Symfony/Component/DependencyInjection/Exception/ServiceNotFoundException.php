<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a non-existent service is requested.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ServiceNotFoundException extends InvalidArgumentException
{
    private $id;
    private $sourceId;

    public function __construct($id, $sourceId = null)
    {
        if (null === $sourceId) {
            $msg = sprintf('You have requested a non-existent service "%s".', $id);
        } else {
            $msg = sprintf('The service "%s" has a dependency on a non-existent service "%s".', $sourceId, $id);
        }

        parent::__construct($msg);

        $this->id = $id;
        $this->sourceId = $sourceId;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSourceId()
    {
        return $this->sourceId;
    }
}
