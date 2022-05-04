<?php

namespace There4\Slim\Test;

use Psr\Http\Message\UriInterface;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\RequestBody;
use Slim\Http\Response;
use Slim\Http\Uri;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Request as SlimRequest;

class WebTestClient
{
    /** @var \Slim\App */
    public $app;

    /** @var  \Slim\Http\Request */
    public $request;

    /** @var  \Slim\Http\Response */
    public $response;

    private $cookies = array();

    public function __construct(App $slim)
    {
        $this->app = $slim;
    }

    public function __call($method, $arguments)
    {
        throw new \BadMethodCallException(strtoupper($method) . ' is not supported');
    }

    public function get($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('get', $path, $data, $optionalHeaders);
    }

    public function post($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('post', $path, $data, $optionalHeaders);
    }

    public function patch($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('patch', $path, $data, $optionalHeaders);
    }

    public function put($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('put', $path, $data, $optionalHeaders);
    }

    public function delete($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('delete', $path, $data, $optionalHeaders);
    }

    public function head($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('head', $path, $data, $optionalHeaders);
    }

    public function options($path, $data = array(), $optionalHeaders = array())
    {
        return $this->request('options', $path, $data, $optionalHeaders);
    }

    // Abstract way to make a request to SlimPHP, this allows us to mock the
    // slim environment
    private function request($method, $path, $data = array(), $optionalHeaders = array())
    {
        //Make method uppercase
        $method = strtoupper($method);
        if ($method === 'GET') {
            $queryString = http_build_query($data);
            $this->request = $this->createRequest($method, $path, $queryString, $optionalHeaders);
        } else {
            $params  = json_encode($data);
            $optionalHeaders['Content-Type'] = 'application/json;charset=utf8';
            $this->request = $this->createRequest($method, $path, null, $optionalHeaders)
                ->withParsedBody($data);
            $this->request->getBody()->write($params);
        }
        // Process request
        $app = $this->app;

        $this->response = $app->handle($this->request);

        // Return the application output.
        return (string)$this->response->getBody();
    }

    public function setCookie($name, $value)
    {
        $this->cookies[$name] = $value;
    }
    /**
     * @param string $method
     * @param string $path
     * @param array  $headers
     * @param array  $cookies
     * @param array  $serverParams
     * @return \Slim\Psr7\Request
     */
    protected function createRequest(
        string $method,
        string $path,
        string $queryString = null,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): \Slim\Psr7\Request {
        if (isset($queryString)) {
            $uri = new \Slim\Psr7\Uri('https', 'localhost', 80, $path, $queryString);
        } else {
            $uri = new \Slim\Psr7\Uri('https', 'localhost', 80, $path);
        }
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);
        $h = new \Slim\Psr7\Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

}
