<?php
/**
 * HTTP HttpKernel 处理请求返回响应
 * User: marhone
 * Date: 2019/1/8
 * Time: 13:22
 */

namespace Tinyfork\Kernel;


use App\Middlewares\ExceptionHandler;
use App\Middlewares\Router;
use App\Providers\ServiceProviderRegister;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Tinyfork\Http\Request;
use Tinyfork\Http\Response;
use Tinyfork\Middleware\Dispatcher;

class HttpKernel
{
    private $root;
    private $booted = false;
    private $debug;
    private $environment;

    /**
     * @var HttpKernel
     */
    private static $instance = null;

    /**
     * @var Container
     */
    private $container;

    /**
     * Singleton
     * HttpKernel constructor.
     * @param string $root
     * @param string $environment
     * @param bool $debug
     */
    private function __construct(string $root, string $environment, bool $debug = false)
    {
        $this->root = $root;
        $this->debug = $debug;
        $this->environment = $environment;
    }

    /**
     * TODO:: NEED A FACTORY
     * @param string $root
     * @param string $environment
     * @param bool $debug
     * @return HttpKernel
     */
    public static function newInstance(string $root, string $environment, bool $debug = false)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($root, $environment, $debug);
        }

        return self::$instance;
    }

    /**
     * 接收请求返回响应, 中间件作用过程
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function handle(Request $request): Response
    {
        $this->boot();

        // 向 容器 注入 Request
        $this->container->set('request', $request);

        // 异常处理中间件
        $middlewares[] = $this->container->get(ExceptionHandler::class);

        // 路由中间件
        $middlewares[] = new Router();

        // 缓存中间件
        // $middlewares[] = $this->container->get(Cache::class);

        // PSR-15 请求处理器
        $runner = (new Dispatcher($middlewares));

        return $runner($request, new Response());
    }

    /**
     * 加载 依赖注入 的容器
     * @throws \Exception
     */
    protected function boot()
    {
        if ($this->booted) {
            return;
        }

        $containerDumpFile = $this->getProjectDir() . '/storage/forks/cache/' . $this->environment . '/container.php';
        // 非调试环境才编译并保存容器
        if (!$this->debug && file_exists($containerDumpFile)) {
            require_once $containerDumpFile;

            $container = new \CachedContainer();
        } else {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.debug', $this->debug);
            $container->setParameter('kernel.project_dir', $this->getProjectDir());
            $container->setParameter('kernel.environment', $this->environment);

            // 加载框架的配置文件
            $loader = new YamlFileLoader($container, new FileLocator($this->getProjectDir() . '/config'));

            try {
                $loader->load('services.yaml');
                $loader->load('services_' . $this->environment . '.yaml');
            } catch (FileLocatorFileNotFoundException $e) {
            }

            // 编译容器
            $container->compile();

            // 保存编译后的容器到文件中
            @mkdir(dirname($containerDumpFile), 0777, true);
            file_put_contents(
                $containerDumpFile,
                (new PhpDumper($container))->dump([
                    'class' => 'CachedContainer'
                ])
            );
        }

        $this->container = $container;

        // 扩展自定义组件
        $this->extend();

        $this->booted = true;
    }

    /**
     * 加载自定义组件到容器中
     * TODO: REFACTOR NEEDED
     * @return void
     * @throws \Exception
     */
    protected function extend()
    {
        $this->container->get(ServiceProviderRegister::class)->register($this->container);
    }

    /**
     * 方便取到容器
     * @return HttpKernel
     * @throws \Exception
     */
    public static function instance(): HttpKernel
    {
        if (is_null(self::$instance)) {
            throw new \Exception('kernel is not instantiated');
        }

        return self::$instance;
    }

    /**
     * 返回容器
     * @return Container
     * @throws \Exception
     */
    public function container()
    {
        if (!$this->booted) {
            throw new \Exception('container is not ready');
        }

        return $this->container;
    }

    private function getProjectDir()
    {
        // stupid code
        return $this->root;
    }
}