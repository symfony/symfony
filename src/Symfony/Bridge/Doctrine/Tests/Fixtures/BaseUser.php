<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

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
}
