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
 * This interface provides an additional level of indirection, so that
 * we can work with abstracted versions of security objects and do
 * not have to save the entire objects.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface SecurityIdentityInterface
{
    /**
     * This method is used to compare two security identities in order to
     * not rely on referential equality.
     *
     * @param SecurityIdentityInterface $identity
     * @return void
     */
    function equals(SecurityIdentityInterface $identity);
}