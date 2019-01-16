<?php
/**
 * Middleware dispatcher
 * User: marhone
 * Date: 2019/1/11
 * Time: 14:20
 */

namespace Tinyfork\Middleware;


use Tinyfork\Http\Request;
use Tinyfork\Http\Response;

class Dispatcher
{
    /**
     * @var array
     */
    private $queue = [];

    /**
     * Dispatcher constructor.
     * @param array $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function __invoke(Request $request, Response $response)
    {
        $middleware = array_shift($this->queue);

        if (!$middleware) {
            $middleware =  function (Request $request, Response $response) {
                return $response;
            };
        }

        return $middleware($request, $response, $this);
    }
}