<?php

namespace Symfony\Component\Security\Acl\Exception;

/**
 * This exception is thrown whenever you change shared properties of more than
 * one ACL of the same class type concurrently.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ConcurrentModificationException extends Exception
{
}