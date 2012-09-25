OptionsResolver Component
=========================

OptionsResolver helps at configuring objects with option arrays.

It supports default values on different levels of your class hierarchy,
option constraints (required vs. optional, allowed values) and lazy options
whose default value depends on the value of another option.

The following example demonstrates a Person class with two required options
"firstName" and "lastName" and two optional options "age" and "gender", where
the default value of "gender" is derived from the passed first name, if
possible, and may only be one of "male" and "female".

    use Symfony\Component\OptionsResolver\OptionsResolver;
    use Symfony\Component\OptionsResolver\OptionsResolverInterface;
    use Symfony\Component\OptionsResolver\Options;

    class Person
    {
        protected $options;

        public function __construct(array $options = array())
        {
            $resolver = new OptionsResolver();
            $this->setDefaultOptions($resolver);

            $this->options = $resolver->resolve($options);
        }

        protected function setDefaultOptions(OptionsResolverInterface $resolver)
        {
            $resolver->setRequired(array(
                'firstName',
                'lastName',
            ));

            $resolver->setDefaults(array(
                'age' => null,
                'gender' => function (Options $options) {
                    if (self::isKnownMaleName($options['firstName'])) {
                        return 'male';
                    }

                    return 'female';
                },
            ));

            $resolver->setAllowedValues(array(
                'gender' => array('male', 'female'),
            ));
        }
    }

We can now easily instantiate a Person object:

    // 'gender' is implicitly set to 'female'
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

Options can be added or changed in subclasses by overriding the `setDefaultOptions`
method:

    use Symfony\Component\OptionsResolver\OptionsResolver;
    use Symfony\Component\OptionsResolver\Options;

    class Employee extends Person
    {
        protected function setDefaultOptions(OptionsResolverInterface $resolver)
        {
            parent::setDefaultOptions($resolver);

            $resolver->setRequired(array(
                'birthDate',
            ));

            $resolver->setDefaults(array(
                // $previousValue contains the default value configured in the
                // parent class
                'age' => function (Options $options, $previousValue) {
                    return self::calculateAge($options['birthDate']);
                }
            ));
        }
    }



Resources
---------

You can run the unit tests with the following command:

    phpunit
