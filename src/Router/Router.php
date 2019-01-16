<?php
/**
 * a simple routing system
 * User: marhone
 * Date: 2019/1/14
 * Time: 12:08
 */

namespace Tinyfork\Router;


use Tinyfork\Http\Request;
use Tinyfork\Http\Response;
use Tinyfork\Middleware\Dispatcher;

class Router
{
    protected $routes = [];

    protected $methods = [
        Request::METHOD_GET,
        Request::METHOD_PUT,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
    ];

    public function __call($method, $arguments)
    {
        if (count($arguments) < 2) {
            throw new \Exception('not enough uri parameters');
        }

        $url = $arguments[0];
        $controller = $arguments[1];
        $url_parameter = null;

        $matches = [];
        preg_match('/(?<=\{)(.+)(?=\})/', $url, $matches);
        if (!empty($matches)) {
            $url_parameter = $matches[0];
            // TODO: 表示动态匹配
            $url = preg_replace('/[\{].*[\}]/U', '{any}', $url);
        }

        $method = strtoupper($method);
        if (in_array($method, $this->methods)) {
            $this->routes[$method][$url] = compact('url', 'controller', 'url_parameter');
        }
    }

    public function group(array $options, \Closure $callable)
    {
        // TODO: STUPID
        $proxy = new self();
        call_user_func($callable, $proxy);

        $mappers = $proxy->routes;
        foreach ($mappers as $method => $routes) {
            foreach ($routes as $route) {

                $route['middleware'] = $options['middleware'] ?? null;

                $prefix = $options['prefix'] ?? null;
                $namespace = $options['namespace'] ?? null;

                if (!empty($prefix)) {
                    $route['url'] = "${prefix}/${route['url']}";
                }

                if (!empty($namespace)) {
                    if (!is_callable($route['controller'])) {
                        $route['controller'] = "${namespace}\\${route['controller']}";
                    }
                }

                $this->routes[$method][$route['url']] = $route;
            }
        }

        return $this;
    }


    /**
     * 寻找路由对应的控制器的处理方法并执行生成响应
     * @param Request $request
     * @param Response $response
     * @return mixed|Response
     * @throws \Exception
     */
    public function dispatch(Request $request, Response $response)
    {
        $httpMethod = $request->getMethod();
        $url = $request->getPathInfo();

        if (strlen($url) > 1) {
            $url = rtrim($url, "\/\\\t\n\r\v");
        }

        if (array_key_exists($httpMethod, $this->routes)) {
            $mappers = $this->routes[$httpMethod];
        } else {
            $response->setStatusCode(Response::HTTP_METHOD_NOT_ALLOWED);
            $response->setContent('method not allowed');
            return $response;
        }


        $route = $this->findRoute($mappers, $url);

        if (!is_null($route['controller'])) {

            // 这里去处理处理单个路由或路由组的中间件
            $middlewares = $route['middleware'];
            if (!empty($middlewares)) {
                // TODO: REFACTOR THIS SH8.
                $runner = (new Dispatcher($middlewares));
                $before = clone $response;
                $after = $runner($request, $response);

                $noChange = $this->diff($before, $after);
                if (!$noChange) {
                    return $after;
                }
            }

            $result = $this->invoke($route);

            if ($result instanceof Response) {
                return $result;
            } else {
                $response->setContent(json_encode($result));
            }

        } else {
            throw new \Exception("not controller for [{$url}]");
        }

        return $response;
    }

    /**
     * // TODO: 如果找不到路由就抛出异常!
     * 路由匹配
     * @param array $mappers
     * @param string $url
     * @return array
     */
    private function findRoute(array $mappers, string $url)
    {
        $route = [
            'controller' => null,
            'url_parameter' => null,
            'url_parameter_value' => null,
            'middleware' => null
        ];

        $routePaths = array_keys($mappers);
        $index = array_search($url, $routePaths);

        $parameter = null;
        if ($index === false) {
            // TODO: 实现URL的多个路由参数的解析
            $paths = array_values(array_filter(explode('/', $url)));
            for($tries = count($paths) - 1; $tries >= 0; $tries--) {
                $copy = $paths;
                // TODO: 表示动态匹配, 暂时不匹配参数类型
                $copy[$tries] = '{any}';
                $supposedPath = '/' . implode('/', $copy);

                $index = array_search($supposedPath, $routePaths);
                if ($index !== false) {
                    $parameter = $paths[$tries];
                    break;
                }
            }
        }

        if ($index !== false) {
            $key = $routePaths[$index];
            $record = $mappers[$key];

            $route['controller'] = $record['controller'];
            $route['url_parameter'] = $record['url_parameter'];
            $route['url_parameter_value'] = $parameter;
            $route['middleware'] = $record['middleware'] ?? null;
        }

        return $route;
    }

    /**
     * 调用 Controller::method() 或 closure
     * @param $route
     * @return mixed
     * @throws \ReflectionException
     */
    private function invoke($route)
    {
        $controller = $route['controller'];
        if (is_callable($controller)) {
            $proxyFunction = new \ReflectionFunction($controller);

            $parameters = $this->resolveParameters($proxyFunction, $route);

            $result = call_user_func($controller, ...$parameters);
        } else {
            list($class, $method) = explode('@', $controller);

            $proxyController = new \ReflectionClass($class);

            // TODO:: 需要 DI !.
            // 解析控制的依赖, 解析控制器依赖的依赖, 解析控制器依赖的依赖的依赖, ...
            $controller = $proxyController->newInstance();

            $proxyMethod = $proxyController->getMethod($method);

            $parameters = $this->resolveParameters($proxyMethod, $route);

            $result = $proxyMethod->invoke($controller, ...$parameters);
        }

        return $result;
    }

    /**
     * @param $proxyFunction \ReflectionFunction | \ReflectionMethod
     * @param $route
     * @return mixed
     */
    private function resolveParameters($proxyFunction, $route)
    {
        $parameters = [];
        $needParameters = $proxyFunction->getParameters();
        foreach ($needParameters as $index => $parameter) {

            if ($parameter->getType()) {
                if ($parameter->getType()->getName() === Request::class) {
                    $request = app('request');
                    $parameters[] = $request;
                }
            } else {
                if (!is_null($route['url_parameter'])) {
                    $parameters[] = $route['url_parameter_value'];
                }
            }
        }

        return $parameters;
    }

    /**
     * @param Response $before
     * @param Response $after
     * @return bool
     */
    private function diff(Response $before, Response $after)
    {
        return ($before->getContent() === $after->getContent())
            && ($before->getStatusCode() === $after->getStatusCode());
    }
}