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
 * This exception is thrown when we cannot locate an ACE that matches the
 * combination of permission masks and security identities.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class NoAceFoundException extends Exception
{
}