<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestAdderVersusSetter
{
    /** @var array */
    private $emails = array();

    /**
     * @param array $emails
     */
    public function setEmails($emails)
    {
        throw new \RuntimeException('Setter must NOT be called while adder exists');
    }

    /**
     * @return array
     */
    public function getEmails()
    {
        return $this->emails;
    }

    /**
     * @param string $email
     */
    public function addEmail($email)
    {
        $this->emails[] = $email;
    }

    /**
     * @param string $email
     */
    public function removeEmail($email)
    {
        $this->emails = array_diff($this->emails, array($email));
    }
}
