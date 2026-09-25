<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Lomkit\Rest\Http\Requests\ActionsRequest;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;

abstract class TestCase extends BaseTestCase
{
    /**
     * lomkit/laravel-rest-api binds its requests as singletons: a real request gets a fresh
     * container, but the requests of one test share it, so each call starts without them.
     */
    private const LOMKIT_REQUESTS = [
        RestRequest::class,
        ActionsRequest::class,
        DestroyRequest::class,
        DetailsRequest::class,
        ForceDestroyRequest::class,
        MutateRequest::class,
        OperateRequest::class,
        RestoreRequest::class,
    ];

    /**
     * @param  string  $method
     * @param  string  $uri
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @param  string|null  $content
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        foreach (self::LOMKIT_REQUESTS as $request) {
            $this->app->forgetInstance($request);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
