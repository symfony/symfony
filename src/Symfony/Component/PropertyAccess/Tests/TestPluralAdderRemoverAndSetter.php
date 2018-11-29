<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

class TestPluralAdderRemoverAndSetter
{
    private $emails = array();

    public function getEmails()
    {
        return $this->emails;
    }

    public function setEmails(array $emails)
    {
        $this->emails = array('foo@email.com');
    }

    public function addEmail($email)
    {
        $this->emails[] = $email;
    }

    public function removeEmail($email)
    {
        $this->emails = array_diff($this->emails, array($email));
    }
}
