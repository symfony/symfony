Stopwatch Component
===================

The Stopwatch component provides a way to profile the execution time and
memory consumption of your code.

Resources
---------

  * `Contributing`_
  * `Report issues`_ and `send Pull Requests`_ in the `main Symfony repository`.

Documentation
-------------

Read the `Profiling Symfony applications`_ article to learn everything about the
Stopwatch component when used inside a Symfony application.

If you use this component in any other PHP application different from Symfony,
the only change you need to make is to instantiate the ``Stopwatch`` class
yourself instead of getting it via the Symfony services:

.. code-block:: php

    use Symfony\Component\Stopwatch\Stopwatch;

    $stopwatch = new Stopwatch();

By default, the stopwatch truncates any sub-millisecond time measure to ``0``,
so you can't measure microseconds or nanoseconds. If you need more precision,
pass ``true`` to the ``Stopwatch`` class constructor to enable full precision:

.. code-block:: php

    use Symfony\Component\Stopwatch\Stopwatch;

    $stopwatch = new Stopwatch(true);

.. _`Contributing`: https://symfony.com/doc/current/contributing/index.html
.. _`Report issues`: https://github.com/symfony/symfony/issues
.. _`send Pull Requests`: https://github.com/symfony/symfony/pulls
.. _`main Symfony repository`: https://github.com/symfony/symfony
.. _`Profiling Symfony applications`: https://symfony.com/doc/current/performance.html#profiling-applications
