Validator Component
===================

This component is based on the [JSR-303 Bean Validation specification] [1] and
enables specifying validation rules for classes using XML, YAML, PHP or
annotations, which can then be checked against instances of these classes.

Usage
-----

The component provides "validation constraints", which are simple objects
containing the rules for the validation. Let's validate a simple string
as an example:

```php
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Length;

$validator = Validation::createValidator();

$violations = $validator->validateValue('Bernhard', new Length(array('min' => 10)));
```

This validation will fail because the given string is shorter than ten
characters. The precise errors, here called "constraint violations",  are
returned by the validator. You can analyze these or return them to the user.
If the violation list is empty, validation succeeded.

Validation of arrays is possible using the `Collection` constraint:

```php
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

$validator = Validation::createValidator();

$constraint = new Assert\Collection(array(
    'name' => new Assert\Collection(array(
        'first_name' => new Assert\Length(array('min' => 101)),
        'last_name' => new Assert\Length(array('min' => 1)),
    )),
    'email' => new Assert\Email(),
    'simple' => new Assert\Length(array('min' => 102)),
    'gender' => new Assert\Choice(array(3, 4)),
    'file' => new Assert\File(),
    'password' => new Assert\Length(array('min' => 60)),
));

$violations = $validator->validateValue($input, $constraint);
```

Again, the validator returns the list of violations.

Validation of objects is possible using "constraint mapping". With such
a mapping you can put constraints onto properties and objects of classes.
Whenever an object of this class is validated, its properties and
method results are matched against the constraints.

```php
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class User
{
    /**
     * @Assert\Length(min = 3)
     * @Assert\NotBlank
     */
    private $name;

    /**
     * @Assert\Email
     * @Assert\NotBlank
     */
    private $email;

    public function __construct($name, $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * @Assert\True(message = "The user should have a Google Mail account")
     */
    public function isGmailUser()
    {
        return false !== strpos($this->email, '@gmail.com');
    }
}

$validator = Validation::createValidatorBuilder()
    ->enableAnnotationMapping()
    ->getValidator();

$user = new User('John Doe', 'john@example.com');

$violations = $validator->validate($user);
```

This example uses the annotation support of Doctrine Common to
map constraints to properties and methods. You can also map constraints
using XML, YAML or plain PHP, if you dislike annotations or don't want
to include Doctrine. Check the documentation for more information about
these drivers.

Resources
---------

  * [Documentation](https://symfony.com/doc/current/book/validation.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: http://jcp.org/en/jsr/detail?id=303
