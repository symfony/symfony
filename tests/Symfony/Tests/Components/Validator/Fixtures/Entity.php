<?php

namespace Symfony\Tests\Components\Validator\Fixtures;

require_once __DIR__.'/EntityParent.php';
require_once __DIR__.'/EntityInterface.php';

/**
 * @Validation({
 *   @NotNull,
 *   @Min(3),
 *   @Choice({"A", "B"}),
 *   @All({@NotNull, @Min(3)}),
 *   @All(constraints={@NotNull, @Min(3)}),
 *   @Collection(fields={
 *     "foo" = {@NotNull, @Min(3)},
 *     "bar" = @Min(5)
 *   })
 * })
 */
class Entity extends EntityParent implements EntityInterface
{
    /**
     * @Validation({
     *   @Choice(choices={"A", "B"}, message="Must be one of %choices%")
     * })
     */
    protected $firstName;

    protected $lastName;

    private $internal;

    public function __construct($internal = null)
    {
        $this->internal = $internal;
    }

    public function getInternal()
    {
        return $this->internal . ' from getter';
    }

    /**
     * @Validation({
     *   @NotNull
     * })
     */
    public function getLastName()
    {
        return $this->lastName;
    }
}