<?php

namespace Tests\Functional;

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

/**
 * This is an example class that shows how you could set up a method that
 * runs the application. 
 */
trait BaseTestCaseTrait
{
    /**
     * Use middleware when running application?
     *
     * @var bool
     */
    protected $withMiddleware = true;
    
    /**
     * Token to use when sending requests
     * 
     * @var string
     */
    protected $token = null;
    
    /**
     * Extra optional headers to be added to mock environment
     */
    protected $optionalHeaders = array();
    
    public function createUser($user, $password) {
    	
    }
    /**
     * 
     * @param unknown $user
     * @param unknown $password
     * @return \Tests\Functional\BaseTestCase
     */
    public function withTokenFor($user, $password) {
    	$response = $this
    		->withBasicAuthentication($user, $password)
    		->runApp('POST', '/token');
    	$decoded = json_decode((string)$response->getBody());
    	$this->token = $decoded->token;
    	$this->optionalHeaders = array(
    			'HTTP_AUTHORIZATION' => "Bearer " . $this->token,
    			'AUTH_TYPE' => 'Bearer'
    	);
    	return $this;
    }
    
    public function withBasicAuthentication($user, $password) {
    	// Prepare a mock environment
    	$this->optionalHeaders = array('PHP_AUTH_USER' => $user, 
    			'PHP_AUTH_PW' => $password,
    			'AUTH_TYPE' => 'Basic',
    			'PHP_AUTH_DIGEST' => "Basic YWRtaW5Aa2x1c2JpYi5iZTp0ZXN0"
    	);
    	return $this;
    }
    
    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @return \Slim\Http\Response
     */
    public function runApp($requestMethod, $requestUri, $requestData = null)
    {
    	 
    	$method = strtoupper($requestMethod);
    	$options = array(
    			'REQUEST_METHOD' => $requestMethod,
    			'REQUEST_URI'    => $requestUri
    	);
    	 
    	// Create a mock environment for testing with
    	$environment = Environment::mock(array_merge($options, $this->optionalHeaders));
//     	print_r($environment);
//     	$environment = Environment::mock(
//             [
//                 'REQUEST_METHOD' => $requestMethod,
//                 'REQUEST_URI' => $requestUri
//             ]
//         );

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if (isset($requestData)) {
            $request = $request->withParsedBody($requestData);
        }

        // Set up a response object
        $response = new Response();

        // Use the application settings
//         $settings = require __DIR__ . '/../../src/settings.php';
        $settings = require __DIR__ . '/../test_settings.php';
        
        // Instantiate the application
        $app = new App($settings);

        // Set up dependencies
        require __DIR__ . '/../../src/dependencies.php';

        // Register middleware
        if ($this->withMiddleware) {
            require __DIR__ . '/../../src/middleware.php';
        }

        // Register routes
        require __DIR__ . '/../../src/routes.php';

        // Process the application
        $response = $app->process($request, $response);

        // Return the response
        return $response;
    }
}
