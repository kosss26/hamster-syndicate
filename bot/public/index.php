<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use QuizBot\Bootstrap\AppBootstrap;

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new AppBootstrap(__DIR__ . '/..');
$app = AppFactory::createFromContainer($bootstrap->getContainer());

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->post('/webhook', function (Request $request, Response $response) use ($bootstrap) {
    $bootstrap->getWebhookHandler()->handle($request);

    return $response->withStatus(200)->withHeader('Content-Type', 'application/json')
        ->withBody(Slim\Psr7\Stream::create(json_encode(['status' => 'ok'])));
});

$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();

