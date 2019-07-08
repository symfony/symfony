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
     *
     * @param int    $id
     * @param string $username
     */
    public function __construct(int $id, string $username)
    {
        $this->id = $id;
        $this->username = $username;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }
}
