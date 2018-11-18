<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use MsgPhp\Domain\Entity\Features\CanBeConfirmed;
use MsgPhp\Domain\Event\DomainEventHandlerInterface;
use MsgPhp\Domain\Event\DomainEventHandlerTrait;
use MsgPhp\User\Entity\User;
use MsgPhp\User\Entity\UserEmail as BaseUserEmail;

/**
 * @ORM\Entity()
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="user", inversedBy="emails")
 * })
 *
 * @final
 */
class UserEmail extends BaseUserEmail implements DomainEventHandlerInterface
{
    use CanBeConfirmed;
    use DomainEventHandlerTrait;

    public function __construct(User $user, string $email, bool $confirm = false)
    {
        parent::__construct($user, $email);

        if ($confirm) {
            $this->confirm();
        } else {
            $this->confirmationToken = bin2hex(random_bytes(32));
        }
    }
}
