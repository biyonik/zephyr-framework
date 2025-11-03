<?php

declare(strict_types=1);

namespace App\Controllers;

use Zephyr\Http\{Request, Response};

/**
 * Test Controller
 * 
 * Example controller for testing the framework.
 * 
 * @author  Ahmet ALTUN
 * @email   ahmet.altun60@gmail.com
 * @github  https://github.com/biyonik
 */
class TestController
{
    /**
     * Index action
     */
    public function index(Request $request): Response
    {
        return Response::success([
            'message' => 'TestController@index works!',
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }

    /**
     * Show action with parameter
     * 
     * Supports both approaches:
     * 1. Getting from Request (recommended)
     * 2. Direct parameter injection
     */
    public function show(Request $request, ?string $id = null): Response
    {
        // Priority: Request param > Direct param
        $id = $request->param('id') ?? $id;
        
        return Response::success([
            'message' => 'TestController@show works!',
            'id' => $id,
            'type' => gettype($id),
            'method' => $request->method(),
        ]);
    }

    /**
     * Store action (POST)
     */
    public function store(Request $request): Response
    {
        $data = $request->all();
        
        return Response::success(
            data: [
                'message' => 'Data received successfully',
                'received' => $data,
            ],
            message: 'Created',
            status: 201
        );
    }
}