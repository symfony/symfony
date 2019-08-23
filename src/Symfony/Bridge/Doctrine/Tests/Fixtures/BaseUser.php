<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class BaseUser.
 */
class BaseUser
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $username;

    private $enabled;

    /**
     * BaseUser constructor.
     */
    public function __construct(int $id, string $username)
    {
        $this->id = $id;
        $this->username = $username;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $allowEmptyString = property_exists(Assert\Length::class, 'allowEmptyString') ? ['allowEmptyString' => true] : [];

        $metadata->addPropertyConstraint('username', new Assert\Length([
            'min' => 2,
            'max' => 120,
            'groups' => ['Registration'],
        ] + $allowEmptyString));
    }
}
