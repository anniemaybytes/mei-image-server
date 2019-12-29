<?php declare(strict_types=1);

namespace Mei;

use Exception;
use Mei\Route as R;
use RunTracy\Helpers\Profiler\Exception\ProfilerException;
use RunTracy\Helpers\Profiler\Profiler;
use Slim\App;
use Slim\Container;

/**
 * Class Dispatcher
 *
 * @package Mei
 */
class Dispatcher extends Singleton
{
    /** @var App $app */
    private $app;

    /** @var array $config */
    private $config;

    /** @var Container $di */
    private $di;

    /**
     * Returns the slim application object
     *
     * @return App
     */
    public static function app(): App
    {
        return self::getInstance()->app;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public static function config($key)
    {
        return self::getInstance()->config[$key];
    }

    /**
     * @return array
     */
    public static function getConfig(): array
    {
        return self::getInstance()->config;
    }

    /**
     * Returns the container object
     *
     * @return Container
     */
    public static function &di(): Container
    {
        return self::getInstance()->di;
    }

    /**
     * @throws ProfilerException
     * @throws Exception
     */
    private function initConfig()
    {
        Profiler::start('initConfig');
        $config = ConfigLoader::load();
        Profiler::finish('initConfig');

        $config['site.public_root'] = BASE_ROOT . '/' . $config['site.public_root'];
        $config['site.images_root'] = BASE_ROOT . '/' . $config['site.images_root'];
        $config['site.deleted_root'] = BASE_ROOT . '/' . $config['site.deleted_root'];
        $this->config = $config;
    }

    /**
     * @throws ProfilerException
     */
    private function initDependencyInjection()
    {
        Profiler::start('initDependencyInjection');
        $this->di = DependencyInjection::get($this->config);
        Profiler::finish('initDependencyInjection');
    }

    /**
     * @throws ProfilerException
     */
    private function initApplication()
    {
        $app = new App($this->di);

        Profiler::start('initRoutes');
        $routes = [
            new R\Main($app),
        ];
        $this->di['routes'] = $routes;
        Profiler::finish('initRoutes');

        $this->app = $app;
    }

    /**
     * Dispatcher constructor.
     *
     * @param $args
     *
     * @throws ProfilerException
     */
    protected function __construct($args)
    {
        $this->initConfig();
        $this->initDependencyInjection();
        $this->initApplication();
        parent::__construct($args);
    }
}
