<?php
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// 1. Load Environment Variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app = AppFactory::create();

// ==========================================
// 2. Middleware Set Up
// ==========================================
$app->addBodyParsingMiddleware(); // Crucial for parsing $_POST data from HTMX
$app->addRoutingMiddleware();     // Crucial for internal route matching
$app->addErrorMiddleware(true, true, true); // Helps catch errors inline

// 2. Define the Authentication Middleware Guard
$authMiddleware = function (Request $request, $handler) {
    $cookies = $request->getCookieParams();
    $token = $cookies['sb_token'] ?? null;

    // 1. Quick check: Is there even a token cookie?
    if (!$token) {
        return handleUnauthorized($request);
    }

    // 2. Validate the token directly with Supabase
    $client = new Client();
    try {
        $res = $client->get($_ENV['SUPABASE_URL'] . '/auth/v1/user', [
            'headers' => [
                'apikey' => $_ENV['SUPABASE_ANON_KEY'],
                'Authorization' => 'Bearer ' . $token,
            ]
        ]);

        // If we get here, the token is 100% valid.
        $userData = json_decode($res->getBody(), true);

        // Optional: Pass the user data along to downstream routes via request attributes
        $request = $request->withAttribute('user', $userData);

        return $handler->handle($request);

    } catch (\Exception $e) {
        // Token was invalid, expired, or Supabase API failed
        return handleUnauthorized($request);
    }
};

/**
 * Helper function to cleanly handle unauthorized responses for both HTMX and Standard requests
 */
function handleUnauthorized(Request $request): \Psr\Http\Message\ResponseInterface {
    $response = new \Slim\Psr7\Response();

    // Clear the bad/expired cookie out of the browser
    $response = $response->withHeader(
        'Set-Cookie',
        'sb_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax'
    );

    if ($request->hasHeader('HX-Request')) {
        return $response->withHeader('HX-Redirect', '/login')->withStatus(401);
    }

    return $response->withHeader('Location', '/login')->withStatus(302);
}

// ==========================================
// PUBLIC ROUTES
// ==========================================

// Automatically redirect root visitors to the login page for now
$app->get('/', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/login')->withStatus(302);
});

// Serve the initial HTML Login Page
// Serve the HTML Login Page with correct Content-Type header & HTMX library
$app->get('/login', function (Request $request, Response $response) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Slim + HTMX + Supabase Auth</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/htmx/2.0.0/htmx.min.js"></script>
        <style>
            body { font-family: sans-serif; margin: 40px; background: #f9f9f9; color: #333; }
            .form-container { max-width: 320px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            input { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
            button { width: 100%; padding: 10px; background: #3ecf8e; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
            button:hover { background: #36b87d; }
            .error { color: #ff4d4d; margin-top: 10px; font-size: 14px; }
        </style>
    </head>
    <body>

        <div class="form-container">
            <h2>Sign In</h2>
            <form hx-post="/auth/login" hx-target="#error-message" hx-swap="innerHTML settle:0s swap:400">>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Log In</button>
            </form>
            <div id="error-message"></div>
        </div>

    </body>
    </html>
    ';

    $response->getBody()->write($html);

    // CRITICAL: Force the browser to parse this as HTML so the script tags activate
    return $response->withHeader('Content-Type', 'text/html');
});

// Handle the HTMX Form Submission
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

        header("Set-Cookie: sb_token=$accessToken; Path=/; HttpOnly; SameSite=Lax");
        return $response->withHeader('HX-Redirect', '/dashboard');

    } catch (\Exception $e) {
        $response->getBody()->write("<div style='color: red;'>Invalid login credentials.</div>");
        return $response->withStatus(400);
    }
});

// ==========================================
// PROTECTED ROUTES (Wrapped in Middleware)
// ==========================================
$app->group('', function ($group) {

    $group->get('/dashboard', function (Request $request, Response $response) {
        // Retrieve the user data array passed down by the middleware
        $user = $request->getAttribute('user');
        $email = $user['email'] ?? 'User';

        $html = "
            <h1>Welcome to your secure dashboard!</h1>
            <p>Logged in as: <strong>" . htmlspecialchars($email) . "</strong></p>
            <a href='/logout'>Logout</a>
        ";

        $response->getBody()->write($html);
        return $response;
    });

    $group->get('/logout', function (Request $request, Response $response) {
        // Instruct the browser to expire the cookie immediately
        $response = $response->withHeader(
            'Set-Cookie',
            'sb_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax'
        );

        // Check if logout was triggered by an HTMX component or a normal link
        if ($request->hasHeader('HX-Request')) {
            return $response->withHeader('HX-Redirect', '/login');
        }

        return $response->withHeader('Location', '/login')->withStatus(302);
    });

})->add($authMiddleware);


// 3. Run the Application
$app->run();
