Profiler Component
==================

Profiler collects information about each run of your application and store them for later analysis.

The profiler is mainly used in the development environment to help you debug
your code and enhance performance; use it in the production environment to
explore problems after the fact.

    use Symfony\Component\Profiler\Profiler;
    use Symfony\Component\Profiler\DataCollector;
    use Symfony\Component\Profiler\Storage\FileProfilerStorage;

    $storage = new FileProfilerStorage('file:/path/to/storage/profiles');
    $profiler = new Profiler($storage);

    // add some data collectors
    $profiler->add(new DataCollector\RequestDataCollector($requestStack));
    $profiler->add(new DataCollector\MemoryDataCollector());
    // ...

    // gather runtime information and create a profile
    $profile = $profiler->profile();

    // profiles are uniquely identified by a token
    $token = $profile->getToken();

    // gather additional information and save to the Storage along with a collection of indexes.
    $profiler->save($profile, array(
        'url' => $event->getRequest()->getUri(),
        'method' => $event->getRequest()->getMethod(),
        'ip' => $event->getRequest()->getClientIp(),
        'status_code' => $event->getResponse()->getStatusCode(),
        'profile_type' => 'http',
    ));

    // in another process, get back a profile
    $profile = $storage->read($token);

    // Searching profiles
    $profiles = $storage->findBy(array('ip' => '127.0.0.1'), 10);

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Profiler/
    $ composer.phar install
    $ phpunit