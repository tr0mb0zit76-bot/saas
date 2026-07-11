<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo 'DB: '.config('database.connections.mysql.database').PHP_EOL;
echo 'Platform emails: '.json_encode(config('saas.platform_admin_emails')).PHP_EOL;

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
