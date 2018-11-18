<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use MsgPhp\User\Entity\Username as BaseUsername;

/**
 * @ORM\Entity()
 *
 * @final
 */
class Username extends BaseUsername
{
}
