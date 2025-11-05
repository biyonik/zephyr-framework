<?php

namespace Tests\Support;
class ClassWithDependency {
    public function __construct(public \stdClass $dep) {}
}