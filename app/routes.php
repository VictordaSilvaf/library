<?php

declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use App\Ordering\Infrastructure\Http\CancelOrderAction;
use App\Ordering\Infrastructure\Http\ConfirmOrderAction;
use App\Ordering\Infrastructure\Http\CreateOrderAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });

    $app->group('/orders', function (Group $group) {
        $group->post('', CreateOrderAction::class);
        $group->post('/{id}/confirm', ConfirmOrderAction::class);
        $group->post('/{id}/cancel', CancelOrderAction::class);
    });
};
