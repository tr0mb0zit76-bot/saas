<?php

namespace App\Support;

use App\Models\User;

final class PlatformAdmin
{
    /**
     * @return list<string>
     */
    public static function allowedEmails(): array
    {
        $raw = config('saas.platform_admin_emails', []);

        if (is_string($raw)) {
            $raw = array_map('trim', explode(',', $raw));
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            $raw,
        )));
    }

    public static function isPlatformAdmin(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));

        return $email !== '' && in_array($email, self::allowedEmails(), true);
    }
}
