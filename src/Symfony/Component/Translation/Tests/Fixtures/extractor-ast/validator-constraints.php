This template is used for translation message extraction tests
<?php

use Symfony\Component\Validator\Constraints as Assert;

class Foo
{
    #[Assert\NotBlank(message: 'message-in-constraint-attribute')]
    public string $bar;

    #[Assert\Length(exactMessage: 'custom Length exact message from attribute from named argument')]
    public string $bar2;

    #[Assert\Length(exactMessage: 'custom Length exact message from attribute from named argument 1/2', minMessage: 'custom Length min message from attribute from named argument 2/2')]
    public string $bar3;

    #[Assert\Isbn('isbn10', 'custom Isbn message from attribute')] // no way to handle those arguments (not named, not in associative array).
    public string $isbn;

    #[Assert\Isbn([
        'type' => 'isbn10',
        'message' => 'custom Isbn message from attribute with options as array',
    ])]
    public string $isbn2;
}

class Foo2
{
    public function index()
    {
        $constraint1 = new Assert\Isbn('isbn10', 'custom Isbn message'); // no way to handle those arguments (not named, not in associative array).
        $constraint2 = new Assert\Isbn([
            'type' => 'isbn10',
            'message' => 'custom Isbn message with options as array',
        ]);
        $constraint3 = new Assert\Isbn(message: 'custom Isbn message from named argument');
        $constraint4 = new Assert\Length(exactMessage: 'custom Length exact message from named argument');
        $constraint5 = new Assert\Length(exactMessage: 'custom Length exact message from named argument 1/2', minMessage: 'custom Length min message from named argument 2/2');
    }
}
