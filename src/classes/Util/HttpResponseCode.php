<?php

namespace Api\Util;


class HttpResponseCode
{
    const OK = 200;
    const CREATED = 201;
    const NO_CONTENT = 204;
    const ALREADY_REPORTED = 208;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const CONFLICT = 409;
    const INTERNAL_ERROR = 500;
    const NOT_IMPLEMENTED = 501;
}