<?php

namespace Tests\Feature;

use Tests\TestCase;
use Zephyr\Http\Request;
use Zephyr\Http\Response;
use Zephyr\Support\Config;

class LifecycleTest extends TestCase
{
    public function test_root_route_returns_welcome_message()
    {
        // / (kök) rotasına bir GET isteği simüle et
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        
        $request = Request::capture(); //
        $this->app->instance(Request::class, $request); //

        $response = $this->app->handle($request); //
        $content = json_decode($response->getContent(), true); //

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($content['success']);
        $this->assertEquals('Zephyr Framework', $content['data']['name']);
    }

    public function test_non_existent_route_returns_404()
    {
        Config::set('app.debug', false); // Hata mesajını test etmek için debug kapalı
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/non-existent-route-12345';
        
        $request = Request::capture();
        $this->app->instance(Request::class, $request);

        $response = $this->app->handle($request);
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($content['success']);
        $this->assertEquals('NOT_FOUND', $content['error']['code']); //
    }
}