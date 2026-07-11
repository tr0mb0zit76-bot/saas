<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo 'DB: '.config('database.connections.mysql.database').PHP_EOL;
echo 'Platform emails: '.json_encode(config('saas.platform_admin_emails')).PHP_EOL;

foreach (['admin@saas.local', 'platform-admin@saas.local'] as $email) {
    $user = User::withoutGlobalScopes()->where('email', $email)->first();

    if ($user === null) {
        echo "$email: NOT FOUND\n";
        continue;
    }

    echo "$email: id={$user->id} active=".(int) $user->is_active;
    echo ' password='.(Hash::check('password', (string) $user->password) ? 'OK' : 'FAIL').PHP_EOL;
}

echo 'Total users: '.User::withoutGlobalScopes()->count().PHP_EOL;
