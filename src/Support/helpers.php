<?php
/* --------------------------------------
 * 定义一些助手函数
 * --------------------------------------
 */


use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Tinyfork\Http\Response;
use Tinyfork\Kernel\HttpKernel;

if (!function_exists('app')) {
    /**
     * @param string|null $id
     * @return mixed
     */
    function app(string $id = null)
    {
        try {
            $container = HttpKernel::instance()->container();
        } catch (Exception $e) {
            $container = null;
        }

        if (is_null($id)) {
            return $container;
        } else {
            try {
                $service = $container->get($id);
            } catch (ServiceNotFoundException $e) {
                $service = $container->getParameter($id);
            } catch (\Exception $e) {
                $service = null;
            }
        }

        return $service;
    }
}

if (!function_exists('response')) {
    function response($content = '', $status = Response::HTTP_OK)
    {
        return new Response($content, $status);
    }
}

if (!function_exists('orm')) {
    function orm()
    {
        // TODO: SILLY MODE
        $orm = app(app('database-orm'));

        return $orm;
    }
}

if (!function_exists('view')) {
    function view($template, array $params = [])
    {
        $template .= '.twig';
        $content = app('twig')->render($template, $params);

        return response($content);
    }
}