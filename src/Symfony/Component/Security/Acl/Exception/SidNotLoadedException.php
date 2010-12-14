<?php

namespace Symfony\Component\Security\Acl\Exception;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This exception is thrown when ACEs for an SID are requested which has not
 * been loaded from the database.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class SidNotLoadedException extends Exception
{
}