<?php

namespace Symfony\Component\AccessControl;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @experimental
 */
readonly class AccessRequest
{
    public function __construct(
        public null|TokenInterface $requester,
        public mixed $attribute,
        public mixed  $subject = null,
        public MetadataBag $metadata = new MetadataBag(),
        public bool $allowIfAllAbstainOrTie = false,
    ) {
    }
}
