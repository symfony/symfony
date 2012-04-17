OptionsParser Component
======================

OptionsParser helps to configure objects with option arrays.

It supports default values on different levels of your class hierarchy,
required options and lazy options where the default value depends on the
concrete value of a different option.

The following example demonstrates a Person class with two required options
"firstName" and "lastName" and two optional options "age" and "gender", where
the default value of "gender" is derived from the passed first name, if
possible.

    use Symfony\Component\OptionsParser\OptionsParser;
    use Symfony\Component\OptionsParser\Options;

    class Person
    {
        protected $options;

        public function __construct(array $options = array())
        {
            $parser = new OptionsParser();
            $this->setOptions($parser);

            $this->options = $parser->parse($options);
        }

        protected function setOptions(OptionsParser $parser)
        {
            $parser->setRequired(array(
                'firstName',
                'lastName',
                'age',
            ));

            $parser->setDefaults(array(
                'age' => null,
                'gender' => function (Options $options) {
                    if (self::isKnownMaleName($options['firstName'])) {
                        return 'male';
                    }

                    return 'female';
                },
            ));

            $parser->setAllowedValues(array(
                'gender' => array('male', 'female'),
            ));
        }
    }

We can now easily instantiate a Person object:

    // 'gender' is implicitely set to 'female'
    $person = new Person(array(
        'firstName' => 'Jane',
        'lastName' => 'Doe',
    ));

We can also override the default values of the optional options:

    $person = new Person(array(
        'firstName' => 'Abdullah',
        'lastName' => 'Mogashi',
        'gender' => 'male',
        'age' => 30,
    ));

Options can be added or changed in subclasses by overriding the `setOptions`
method:

    use Symfony\Component\OptionsParser\OptionsParser;
    use Symfony\Component\OptionsParser\Options;

    class Employee extends Person
    {
        protected function setOptions(OptionsParser $parser)
        {
            parent::setOptions($parser);

            $parser->setRequired(array(
                'birthDate',
            ));

            $parser->setDefaults(array(
                // $previousValue contains the default value configured in the
                // parent class
                'age' => function (Options $options, $previousValue) {
                    return self::configureAgeFromBirthDate($options['birthDate']);
                }
            ));
        }
    }

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/OptionsParser/