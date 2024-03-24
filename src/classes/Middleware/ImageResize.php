<?php

/*
 * This file is part of the Slim Image Resize middleware
 *
 * Copyright (c) 2014 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-image-resize
 *   
 * 2022 - Adapted to run on slim v4
 *
 */

namespace Api\Middleware;

use Intervention\Image\Image;
use Api\Middleware\ImageResize\DefaultMutator;
//use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
// use Slim\Http\Request;
// use Slim\Http\Response;
use Slim\Http\Body;

class ImageResize
{

    protected $options;
    public $mutator;

    public function __construct($options = null, $mutator = null)
    {

        /* Default options. */
        $this->options = [
            "extensions" => ["jpg", "jpeg", "png", "gif"], 
            "cache" => "cache", 
            "sizes" => null, 
            "secret" => null, 
            "mutator" => new DefaultMutator()
        ];

        if ($options) {
            $this->options = array_merge($this->options, (array)$options);
        }

        /* TODO: Use proper DI. */
        if (isset($mutator)) {
        	$this->mutator = $mutator;
        } else {
        	$this->mutator = $this->options["mutator"];
        }
        unset($this->options["mutator"]);
    }

    /**
     * middleware invokable class (Slim v4)
     *
     * @param  ServerRequest  $request PSR-7 request
     * @param  RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $url = $request->getUri()->getPath();
        $path = parse_url($url, PHP_URL_PATH);
        $folder = substr($path, 0,strrpos($path,'/')+1);

        if ($matched = $this->mutator->parse($path)) {
            /* Extract array variables to current symbol table */
            extract($matched);
        };

        $response = new \Slim\Psr7\Response();
        if ($matched && $this->allowed(["extension" => $extension, "size" => $size, "signature" => $signature])) {
            $this->mutator->execute();

            /* When requested save image to cache folder. */
            if ($this->options["cache"]) {
                /* TODO: Make this pretty. */
                $cache = $_SERVER["DOCUMENT_ROOT"] . $folder . "/" .
                    $this->options["cache"] . $path;
                $dir = pathinfo($cache, PATHINFO_DIRNAME);
                if (false === is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $this->mutator->save($cache);
            }

            $response = $response->withHeader("Content-type", $this->mutator->mime());
            $response->getBody()->write($this->mutator->encode()->getEncoded());
        } else {
            $response = $handler->handle($request);
        }
        return $response; // continue
    }

    public function allowed($parameters = [])
    {
        $extension = null;
        $size = null;
        extract($parameters);
        return $this->allowedExtension($extension) &&
               $this->allowedSize($size) &&
               $this->validSignature($parameters);
    }

    public function allowedExtension($extension = null)
    {
        return $extension && in_array($extension, $this->options["extensions"]);
    }

    public function allowedSize($size = null)
    {
        if (false == !!$this->options["sizes"]) {
            /* All sizes are allowed. */
            return true;
        } else {
            /* Only sizes passed in as array are allowed. */
            return is_array($this->options["sizes"]) && in_array($size, $this->options["sizes"]);
        }
    }

    public function validSignature($parameters = null)
    {
        /* Default arguments. */
        $arguments = ["size" => null, "signature" => null];

        if ($parameters) {
            $arguments = array_merge($arguments, (array)$parameters);
        }

        if (false == !!$this->options["secret"] && null === $arguments["signature"]) {
            /* No secret is set or passed. All shall pass. */
            return true;
        } else {
            $signature = self::signature([
                "size" => $arguments["size"], 
                "secret" => $this->options["secret"]
            ]);

            return $arguments["signature"] === $signature;
        }
    }

    public static function signature($parameters = null)
    {
        /* Default arguments. */
        $arguments = [
            "size" => null, 
            "secret" => null, 
            "width" => null, 
            "height" => null
        ];

        if ($parameters) {
            $arguments = array_merge($arguments, (array)$parameters);
        }

        $sha1 = sha1("{$arguments["size"]}:{$arguments["secret"]}");

        /* We use only 16 first characters. Secure enough. */
        return substr($sha1, 0, 16);
    }
}
