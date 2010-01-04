<?php

/*
 * This file is part of the Lime test framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Exception thrown by all invokation matchers.
 *
 * This exception is thrown by the method invoke() of all invokation matchers
 * implementing LimeMockInvocationMatcherInterface if the invokation is not
 * accepted.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationMatcherException.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeMockInvocationMatcherInterface
 */
class LimeMockInvocationMatcherException extends Exception
{
}