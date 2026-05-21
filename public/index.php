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
    // --- BYPASS AUTH FOR PREFLIGHT HANDSHAKES ---
    if ($request->getMethod() === 'OPTIONS') {
        return $handler->handle($request);
    }

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

    // --- ADD CORS FALLBACKS SO STORYBOOK CAN READ THE 401/302 SAFELY ---
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006')
        ->withHeader('Access-Control-Allow-Credentials', 'true');

    if ($request->hasHeader('HX-Request')) {
        return $response->withHeader('HX-Redirect', '/login')->withStatus(401);
    }

    return $response->withHeader('Location', '/login')->withStatus(302);
}

// --- STORYBOOK DEV ROUTES ---
// Since we don't have a container, we can read our environment status directly from $_ENV
if (($_ENV['APP_ENV'] ?? 'development') === 'development') {

    // Add explicit CORS headers to allow Storybook (port 6006) to fetch from Slim (port 8000)
    $app->options('/storybook/render', function (Request $request, Response $response) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    });

    // Pass the global $twig object directly into our route scope using "use ($twig)"
    $app->get('/storybook/render', function (Request $request, Response $response) use ($twig) {
        $queryParams = $request->getQueryParams();
        $template = $queryParams['id'] ?? null;

        $args = $queryParams['args'] ?? [];
        if (is_string($args)) {
            $args = json_decode($args, true) ?? [];
        }

        if (!$template) {
            $response->getBody()->write('Error: Missing template "id" target.');
            return $response->withStatus(400);
        }

        try {
            // Render the template using the standalone $twig instance
            // Note: Slim's custom Twig view helper uses ->fetch(), or we can use the environment engine directly
            $html = $twig->getEnvironment()->render($template, $args);

            $response->getBody()->write($html);

            return $response
                ->withHeader('Content-Type', 'text/html')
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006'); // Allow Storybook to read the HTML

        } catch (\Exception $e) {
            $response->getBody()->write('<h3>Twig Render Error</h3><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
            return $response
                ->withStatus(500)
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006');
        }
    });
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

$app->group('', function ($group) use ($twig) {

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

    $group->map(['GET', 'OPTIONS'], '/api/profile-card', function (Request $request, Response $response) use ($twig) {

        // 1. Handle preflight handshake completely and exit early
        if ($request->getMethod() === 'OPTIONS') {
            return $response
                ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, HX-Request, HX-Trigger, HX-Target, HX-Current-URL')
                ->withStatus(200);
        }

        // 2. Safely capture the authenticated user or provide an explicit sandbox fallback
        $user = $request->getAttribute('user');
        $userId = $user['id'] ?? null;
        $email = $user['email'] ?? 'sandbox-user@identity.ai'; // Fallback display for Storybook preview loops

        $displayName = 'Sandbox Explorer';
        $website = 'https://storybook.js.org';
        $bio = 'Simulating structural element parameters within isolated component viewports.';

        // 3. Only query Supabase if a genuine token was decoded by the middleware
        if ($userId) {
            $client = new \GuzzleHttp\Client();
            try {
                $res = $client->get($_ENV['SUPABASE_URL'] . '/rest/v1/profiles?id=eq.' . $userId, [
                    'headers' => [
                        'apikey' => $_ENV['SUPABASE_ANON_KEY'],
                        'Authorization' => 'Bearer ' . ($_COOKIE['sb_token'] ?? ''),
                        'Accept' => 'application/json'
                    ]
                ]);

                $profileData = json_decode($res->getBody(), true);
                if (!empty($profileData)) {
                    $displayName = $profileData[0]['display_name'] ?? 'No Name Set';
                    $website = $profileData[0]['website'] ?? '';
                    $bio = $profileData[0]['bio'] ?? 'No bio written yet.';
                }
            } catch (\Exception $e) {
                // Keep development defaults on exception timeouts
            }
        }

        $html = $twig->getEnvironment()->render('partials/user-profile.html.twig', [
            'render_content_only' => true,
            'email' => $email,
            'display_name' => $displayName,
            'website' => $website,
            'bio' => $bio
        ]);

        $response->getBody()->write($html);

        // 4. Return the finalized payload with explicit cross-origin clearance
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:6006')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    });

})->add($authMiddleware);

$app->run();
