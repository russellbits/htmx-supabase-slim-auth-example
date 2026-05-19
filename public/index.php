<?php
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$app = AppFactory::create();
$twig = Twig::create(__DIR__ . '/../templates', ['cache' => false]);

$dotenv->load();
$app->add(TwigMiddleware::create($app, $twig));
$app->addBodyParsingMiddleware(); // Ensures $request->getParsedBody() captures our Web Component form values
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$authMiddleware = function (Request $request, $handler) {
    $cookies = $request->getCookieParams();
    $token = $cookies['sb_token'] ?? null;

    if (!$token) {
        return handleUnauthorized($request);
    }

    $client = new Client();
    try {
        $res = $client->get($_ENV['SUPABASE_URL'] . '/auth/v1/user', [
            'headers' => [
                'apikey' => $_ENV['SUPABASE_ANON_KEY'],
                'Authorization' => 'Bearer ' . $token,
            ]
        ]);

        $userData = json_decode($res->getBody(), true);
        $request = $request->withAttribute('user', $userData);

        return $handler->handle($request);

    } catch (\Exception $e) {
        return handleUnauthorized($request);
    }
};

function handleUnauthorized(Request $request): \Psr\Http\Message\ResponseInterface {
    $response = new \Slim\Psr7\Response();
    $response = $response->withHeader(
        'Set-Cookie',
        'sb_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax'
    );

    if ($request->hasHeader('HX-Request')) {
        return $response->withHeader('HX-Redirect', '/login')->withStatus(401);
    }

    return $response->withHeader('Location', '/login')->withStatus(302);
}

// --- ROUTES ---

$app->get('/', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/login')->withStatus(302);
});

$app->get('/login', function (Request $request, Response $response) {
    $view = Twig::fromRequest($request);
    return $view->render($response, 'login.html.twig');
});

$app->post('/auth/login', function (Request $request, Response $response) {
    $params = (array)$request->getParsedBody();
    $email = $params['email'] ?? '';
    $password = $params['password'] ?? '';

    $client = new Client();

    try {
        $res = $client->post($_ENV['SUPABASE_URL'] . '/auth/v1/token?grant_type=password', [
            'headers' => [
                'apikey' => $_ENV['SUPABASE_ANON_KEY'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'email' => $email,
                'password' => $password,
            ]
        ]);

        $data = json_decode($res->getBody(), true);
        $accessToken = $data['access_token'];

        // Authentication Success: Set the auth token cookie
        $response = $response->withHeader('Set-Cookie', "sb_token=$accessToken; Path=/; HttpOnly; SameSite=Lax");

        // Instruct HTMX to handle a clean, top-level window redirect to the dashboard
        return $response->withHeader('HX-Redirect', '/dashboard');

    } catch (\Exception $e) {
        $view = Twig::fromRequest($request);

        // Authentication Failure: Target only the inner HTML container of `#error-message`
        // We drop down to a 200 OK status code here because some HTMX installations reject 400-level
        // responses from swapping targets unless configured with `hx-select` or extensions.
        return $view->render($response, 'partials/login-error.html.twig', [
            'error_message' => 'Invalid login credentials.'
        ])->withStatus(200);
    }
});

$app->group('', function ($group) {

    $group->get('/dashboard', function (Request $request, Response $response) {
        $user = $request->getAttribute('user');
        $email = $user['email'] ?? 'User';

        $view = Twig::fromRequest($request);
        return $view->render($response, 'dashboard.html.twig', [
            'email' => $email
        ]);
    });

    $group->get('/logout', function (Request $request, Response $response) {
        $response = $response->withHeader(
            'Set-Cookie',
            'sb_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax'
        );

        if ($request->hasHeader('HX-Request')) {
            return $response->withHeader('HX-Redirect', '/login');
        }

        return $response->withHeader('Location', '/login')->withStatus(302);
    });

})->add($authMiddleware);

$app->run();
