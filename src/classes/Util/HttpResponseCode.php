<?php

namespace Api\Util;


class HttpResponseCode
{
    public const OK = 200;
    public const CREATED = 201;
    public const NO_CONTENT = 204;
    public const ALREADY_REPORTED = 208;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const CONFLICT = 409;
    public const INTERNAL_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
}