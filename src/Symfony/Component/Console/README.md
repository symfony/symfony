Console Component
=================

Even if we are talking about a web framework, having some tools to manage
your project from the command line is nice. In Symfony2, we use the console
to generate CRUDs, update the database schema, etc. It's not required, but
it is really convenient and it can boost your productivity a lot.

This example shows how to create a command line tool very easily:

```
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application();
$console
    ->register('ls')
    ->setDefinition(array(
        new InputArgument('dir', InputArgument::REQUIRED, 'Directory name'),
    ))
    ->setDescription('Displays the files in the given directory')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $dir = $input->getArgument('dir');

        $output->writeln(sprintf('Dir listing for <info>%s</info>', $dir));
    })
;

$console->run();
```

With only 10 lines of code or so, you have access to a lot of features like output
coloring, input and output abstractions (so that you can easily unit-test your
commands), validation, automatic help messages, and a lot more. It's really powerful.

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/Console
