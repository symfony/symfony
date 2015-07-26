README
======

What is Symfony?
-----------------

Symfony is a PHP 5.3 full-stack web framework. It is written with speed and
flexibility in mind. It allows developers to build better and easy to maintain
websites with PHP.

Symfony can be used to develop all kind of websites, from your personal blog
to high traffic ones like Dailymotion or Yahoo! Answers.

Requirements
------------

Symfony is only supported on PHP 5.3.3 and up.

Be warned that PHP versions before 5.3.8 are known to be buggy and might not
work for you:

 * before PHP 5.3.4, if you get "Notice: Trying to get property of
   non-object", you've hit a known PHP bug (see
   https://bugs.php.net/bug.php?id=52083 and
   https://bugs.php.net/bug.php?id=50027);

 * before PHP 5.3.8, if you get an error involving annotations, you've hit a
   known PHP bug (see https://bugs.php.net/bug.php?id=55156).

 * PHP 5.3.16 has a major bug in the Reflection subsystem and is not suitable to
   run Symfony (https://bugs.php.net/bug.php?id=62715)

Installation
------------

The best way to install Symfony is to use the [official Symfony Installer][7].
It allows you to start a new project based on the version you want.

Documentation
-------------

The "[Quick Tour][1]" tutorial gives you a first feeling of the framework. If,
like us, you think that Symfony can help speed up your development and take
the quality of your work to the next level, read the official
[Symfony documentation][2].

Contributing
------------

Symfony is an open source, community-driven project. If you'd like to contribute,
please read the [Contributing Code][3] part of the documentation. If you're submitting
a pull request, please follow the guidelines in the [Submitting a Patch][4] section
and use [Pull Request Template][5].

Running Symfony Tests
----------------------

Information on how to run the Symfony test suite can be found in the
[Running Symfony Tests][6] section.

[1]: https://symfony.com/get_started
[2]: https://symfony.com/doc/current/
[3]: https://symfony.com/doc/current/contributing/code/index.html
[4]: https://symfony.com/doc/current/contributing/code/patches.html#check-list
[5]: https://symfony.com/doc/current/contributing/code/patches.html#make-a-pull-request
[6]: https://symfony.com/doc/master/contributing/code/tests.html
[7]: https://symfony.com/doc/current/book/installation.html#installing-the-symfony-installer
