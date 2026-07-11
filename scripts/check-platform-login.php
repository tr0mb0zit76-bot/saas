<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo 'DB: '.config('database.connections.mysql.database').PHP_EOL;
echo 'APP_URL: '.config('app.url').PHP_EOL;
echo 'session.secure: '.json_encode(config('session.secure')).PHP_EOL;
echo 'Platform emails: '.json_encode(config('saas.platform_admin_emails')).PHP_EOL;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

foreach ([
    'plain HTTP' => ['HTTP_HOST' => 'platform.saas.local'],
    'X-Forwarded-Proto: https' => [
        'HTTP_HOST' => 'platform.saas.local',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ],
] as $label => $server) {
    $request = Illuminate\Http\Request::create('http://platform.saas.local/login', 'GET', [], [], [], $server);
    $response = $kernel->handle($request);
    $secureFlags = 0;
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->isSecure()) {
            $secureFlags++;
        }
    }
    echo "Cookies ({$label}): ".count($response->headers->getCookies())." set, {$secureFlags} with Secure flag".PHP_EOL;
    $kernel->terminate($request, $response);
}

$user = User::withoutGlobalScopes()->where('email', 'admin@saas.local')->first();
$platformAdmin = User::withoutGlobalScopes()->where('email', 'platform-admin@saas.local')->first();

foreach (['admin@saas.local' => $user, 'platform-admin@saas.local' => $platformAdmin] as $email => $row) {
    if ($row === null) {
        echo "$email: NOT FOUND\n";
        continue;
    }
    echo "$email: id={$row->id} active=".(int) $row->is_active.PHP_EOL;
    echo "$email password (password): ".(Hash::check('password', (string) $row->password) ? 'OK' : 'FAIL').PHP_EOL;
}

if ($user === null && $platformAdmin === null) {
    echo 'Total users: '.User::withoutGlobalScopes()->count().PHP_EOL;
    exit(1);
}
