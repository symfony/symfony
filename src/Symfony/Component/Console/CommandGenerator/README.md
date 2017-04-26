
CommandGenerator is a library that complements the symfony console component
providing a tool for generating loads of commands dynamically from a given
source (.json file, method returning an array, .yaml file, etc.).

Example:

```php
$commandManager = new commandManager(new commandDiscovery(new commandYourResourceBuilder($your_source_file)), $YourCommandClassName);

$application = new Application();
$application->addCommands($commandManager->generateCommands());
$application->run();
```

All you need to do is to create a commandManager and to pass the generated commands
to your console application like in the example above.

You should implement the "CommandResourceBuilderInterface" interface for returning
an array of the definitions that can be used for your "CustomCommand" class for
building your commands.

If your customCommandClass extends from Command you can use the getCommandDefinition()
method for retrieving a definition array and create parameters dynamically.

A case of use could be a Guzzle client which contains the api services definitions in a json file.
Your "commandYourResourceBuilder" Class could build the command definitions from this json file
being able to generate commands for every service call defined in the json file.
