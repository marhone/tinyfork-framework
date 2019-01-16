<?php
/**
 * Request
 * User: marhone
 * Date: 2019/1/11
 * Time: 13:39
 */

namespace Tinyfork\Http;


use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest
{
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    // HTTP POST
    public function post(string $key)
    {
        $contentType = $this->getContentType();

        if ($contentType === 'json') {
            $body = json_decode($this->getContent());
            try {
                return $body->$key;
            } catch (\Exception $exception) {
            }
        }

        return $this->get($key);
    }

    // HTTP PUT
    public function put(string $key)
    {
        $contentType = $this->getContentType();

        if ($contentType === 'json') {
            $body = json_decode($this->getContent());
            try {
                return $body->$key;
            } catch (\Exception $exception) {
            }
        }

        return $this->get($key);
    }
}