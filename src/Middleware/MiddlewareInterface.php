<?php
/**
 * Middleware interface
 * User: marhone
 * Date: 2019/1/11
 * Time: 14:15
 */

namespace Tinyfork\Middleware;


use Tinyfork\Http\Request;
use Tinyfork\Http\Response;

interface MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next);
}