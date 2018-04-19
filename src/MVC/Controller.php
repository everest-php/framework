<?php

namespace everest\MVC;

abstract class Controller
{
    public function ok($result = null) {
        return $this->statusCode(200, $result);
    }
    public function created($result = null) {
        return $this->statusCode(201, $result);
    }
    public function accepted($result = null) {
        return $this->statusCode(202, $result);
    }
    public function forbidden($result = null) {
        return $this->statusCode(403, $result);
    }
    public function badRequest($result = null) {
        return $this->statusCode(400, $result);
    }
    public function unauthorized($result = null) {
        return $this->statusCode(401, $result);
    }
    public function notFound($result = null) {
        return $this->statusCode(404, $result);
    }
    public function statusCode(int $code, $result = null) {
        http_response_code($code);
        return $result;
    }
}