<?php
namespace Symfony\Framework\Bundle;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;
abstract class Bundle extends ContainerAware implements BundleInterface {
    protected $name;
    protected $namespacePrefix;
    protected $path;
    protected $reflection;
    public function boot() { }
    public function shutdown() { }
    public function getName() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->name; }
    public function getNamespacePrefix() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->namespacePrefix; }
    public function getPath() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->path; }
    public function getReflection() {
        if (null === $this->name) {
            $this->initReflection(); }
        return $this->reflection; }
    public function registerExtensions(ContainerBuilder $container) {
        if (!$dir = realpath($this->getPath().'/DependencyInjection')) {
            return array(); }
        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);
        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\DependencyInjection';
        foreach ($finder as $file) {
            $class = $prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php');
            if ('Extension' === substr($class, -9)) {
                $container->registerExtension(new $class()); } } }
    public function registerCommands(Application $application) {
        if (!$dir = realpath($this->getPath().'/Command')) {
            return; }
        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);
        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->addCommand($r->newInstance()); } } }
    protected function initReflection() {
        $tmp = dirname(str_replace('\\', '/', get_class($this)));
        $this->namespacePrefix = str_replace('/', '\\', dirname($tmp));
        $this->name = basename($tmp);
        $this->reflection = new \ReflectionObject($this);
        $this->path = dirname($this->reflection->getFilename()); } }
namespace Symfony\Framework\Bundle;
interface BundleInterface {
    public function boot();
    public function shutdown(); }
namespace Symfony\Framework;
use Symfony\Framework\Bundle\Bundle;
class KernelBundle extends Bundle {
    public function boot() {
        if ($this->container->has('error_handler')) {
            $this->container['error_handler']; } } }
namespace Symfony\Framework\DependencyInjection;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
class KernelExtension extends Extension {
    public function testLoad($config, ContainerBuilder $container) {
        $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
        $loader->load('test.xml'); }
    public function sessionLoad($config, ContainerBuilder $container) {
        if (!$container->hasDefinition('session')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('session.xml'); }
        if (isset($config['default_locale'])) {
            $container->setParameter('session.default_locale', $config['default_locale']); }
        if (isset($config['class'])) {
            $container->setParameter('session.class', $config['class']); }
        foreach (array('name', 'lifetime', 'path', 'domain', 'secure', 'httponly', 'cache_limiter', 'pdo.db_table') as $name) {
            if (isset($config['session'][$name])) {
                $container->setParameter('session.options.'.$name, $config['session'][$name]); } }
        if (isset($config['session']['class'])) {
            $class = $config['session']['class'];
            if (in_array($class, array('Native', 'Pdo'))) {
                $class = 'Symfony\\Component\\HttpFoundation\\SessionStorage\\'.$class.'SessionStorage'; }
            $container->setParameter('session.session', 'session.session.'.strtolower($class)); } }
    public function configLoad($config, ContainerBuilder $container) {
        if (!$container->hasDefinition('event_dispatcher')) {
            $loader = new XmlFileLoader($container, array(__DIR__.'/../Resources/config', __DIR__.'/Resources/config'));
            $loader->load('services.xml');
            if ($container->getParameter('kernel.debug')) {
                $loader->load('debug.xml');
                $container->setDefinition('event_dispatcher', $container->findDefinition('debug.event_dispatcher'));
                $container->setAlias('debug.event_dispatcher', 'event_dispatcher'); } }
        if (isset($config['charset'])) {
            $container->setParameter('kernel.charset', $config['charset']); }
        if (array_key_exists('error_handler', $config)) {
            if (false === $config['error_handler']) {
                $container->getDefinition('error_handler')->setMethodCalls(array()); } else {
                $container->getDefinition('error_handler')->addMethodCall('register', array());
                $container->setParameter('error_handler.level', $config['error_handler']); } } }
    public function getXsdValidationBasePath() {
        return false; }
    public function getNamespace() {
        return 'http://www.symfony-project.org/schema/dic/symfony/kernel'; }
    public function getAlias() {
        return 'kernel'; } }
namespace Symfony\Framework\Debug;
class ErrorHandler {
    protected $levels = array(
        E_WARNING           => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
    );
    protected $level;
    public function __construct($level = null) {
        $this->level = null === $level ? error_reporting() : $level; }
    public function register() {
        set_error_handler(array($this, 'handle')); }
    public function handle($level, $message, $file, $line, $context) {
        if (0 === $this->level) {
            return false; }
        if (error_reporting() & $level && $this->level & $level) {
            throw new \ErrorException(sprintf('%s: %s in %s line %d', isset($this->levels[$level]) ? $this->levels[$level] : $level, $message, $file, $line)); }
        return false; } }
namespace Symfony\Framework;
class ClassCollectionLoader {
    static protected $loaded;
    static public function load($classes, $cacheDir, $name, $autoReload, $adaptive = false) {
                if (isset(self::$loaded[$name])) {
            return; }
        self::$loaded[$name] = true;
        $classes = array_unique($classes);
        if ($adaptive) {
                        $classes = array_diff($classes, get_declared_classes(), get_declared_interfaces());
                        $name = $name.'-'.substr(md5(implode('|', $classes)), 0, 5); }
        $cache = $cacheDir.'/'.$name.'.php';
                $reload = false;
        if ($autoReload) {
            $metadata = $cacheDir.'/'.$name.'.meta';
            if (!file_exists($metadata) || !file_exists($cache)) {
                $reload = true; } else {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));
                if ($meta[1] != $classes) {
                    $reload = true; } else {
                    foreach ($meta[0] as $resource) {
                        if (!file_exists($resource) || filemtime($resource) > $time) {
                            $reload = true;
                            break; } } } } }
        if (!$reload && file_exists($cache)) {
            require_once $cache;
            return; }
        $files = array();
        $content = '';
        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class)); }
            $r = new \ReflectionClass($class);
            $files[] = $r->getFileName();
            $content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName())); }
                if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true); }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));
        if ($autoReload) {
                        self::writeCacheFile($metadata, serialize(array($files, $classes))); } }
    static protected function writeCacheFile($file, $content) {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);
            return; }
        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file)); } }
namespace Symfony\Framework;
use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;
class EventDispatcher extends BaseEventDispatcher {
    public function setContainer(ContainerInterface $container) {
        foreach ($container->findTaggedServiceIds('kernel.listener') as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $container->get($id)->register($this, $priority); } } }
