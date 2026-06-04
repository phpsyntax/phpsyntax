<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$factory = new Nette\Http\RequestFactory;
$httpRequest = $factory->fromGlobals();

$router = RouterFactory::createRouter();

$params = $router->match($httpRequest);

$url = $router->constructUrl($params, $httpRequest->getUrl());

?>
Current URL: <?= htmlspecialchars((string) $httpRequest->getUrl()) ?><br>
Params: <?php dump($params) ?>
URL: <?= htmlspecialchars($url) ?><br>
