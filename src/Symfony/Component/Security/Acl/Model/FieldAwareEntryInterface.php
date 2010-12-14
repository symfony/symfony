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
 * Interface for entries which are restricted to specific fields
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface FieldAwareEntryInterface
{
    function getField();
}