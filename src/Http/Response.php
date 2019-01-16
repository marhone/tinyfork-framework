<?php
/**
 * Response
 * User: marhone
 * Date: 2019/1/11
 * Time: 13:45
 */

namespace Tinyfork\Http;


use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Response extends SymfonyResponse
{
    public function __construct(string $content = '', int $status = 200, array $headers = array())
    {
        parent::__construct($content, $status, $headers);
    }

    public function json($content, $status = Response::HTTP_OK)
    {
        $content = json_encode($content);

        $this->setStatusCode($status);
        $this->setContent($content);

        $this->headers->set('Content-Type', 'application/json; utf-8');
        return $this;
    }
}