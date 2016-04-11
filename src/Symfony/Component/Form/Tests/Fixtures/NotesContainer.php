<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class NotesContainer
{
    private $notes;

    public function __construct()
    {
        $this->notes = new ArrayCollectionWithoutOffsetUnset();
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function addNote($note)
    {
        $this->notes->add($note);
    }

    public function removeNote($note)
    {
        $this->notes->removeElement($note);
    }
}
