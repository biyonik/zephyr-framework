<?php

namespace Tests\Support;

class TestController {
    public function method(\stdClass $dep, string $param) {
        return $dep->name . '-' . $param;
    }
}