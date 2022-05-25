<?php

/*
 * This file is part of PSR-7 JSON Web Token Authentication middleware
 *
 * Copyright (c) 2015-2017 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-jwt-auth
 *
 */

namespace Api\Middleware\Jwt;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use Tuupola\Middleware\JwtAuthentication\RuleInterface;

/**
 * Rule to decide by request path whether the request should be authenticated or not.
 */

class JwtCustomRule implements RuleInterface
{
    /**
     * Stores all the options passed to the rule
     */
    protected $options = [
        "path" => ["/"],
        "ignore" => [],
    	"getignore" => [],
    	"postignore" => []
    ];

    /**
     * Create a new rule instance
     *
     * @param string[] $options
     * @return void
     */
    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * @param \Psr\Http\Message\RequestInterface $request
     * @return boolean
     */
    public function __invoke(ServerRequestInterface $request): bool
    {
        $uri = "/" . $request->getUri()->getPath();
        $uri = preg_replace("#/+#", "/", $uri);

        /* If request path is matches passthrough should not authenticate. */
        foreach ((array)$this->options["ignore"] as $passthrough) {
            $passthrough = rtrim($passthrough, "/");
            if (!!preg_match("@^{$passthrough}(/.*)?$@", $uri)) {
                return false;
            }
        }

        if ($request->getMethod() == "GET") {
        	foreach ((array)$this->options["getignore"] as $passthrough) {
        		$passthrough = rtrim($passthrough, "/");
        		if (!!preg_match("@^{$passthrough}(/.*)?$@", $uri)) {
        			return false;
        		}
        	}
        }
        if ($request->getMethod() == "POST") {
        	foreach ((array)$this->options["postignore"] as $passthrough) {
        		$passthrough = rtrim($passthrough, "/");
        		if (!!preg_match("@^{$passthrough}(/.*)?$@", $uri)) {
        			return false;
        		}
        	}
        }
        /* Otherwise check if path matches and we should authenticate. */
        foreach ((array)$this->options["path"] as $path) {
            $path = rtrim($path, "/");
            if (!!preg_match("@^{$path}(/.*)?$@", $uri)) {
                return true;
            }
        }
        return false;
    }
}
