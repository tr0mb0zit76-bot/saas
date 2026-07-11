<?php

namespace App\Support;

use App\Models\Tenant;

final class TenantContext
{
    private static ?Tenant $tenant = null;

    private static bool $bypass = false;

    public static function set(?Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return self::$tenant;
    }

    public static function id(): ?int
    {
        return self::$tenant?->id;
    }

    public static function bypass(bool $bypass = true): void
    {
        self::$bypass = $bypass;
    }

    public static function isBypassed(): bool
    {
        return self::$bypass;
    }

    public static function clear(): void
    {
        self::$tenant = null;
        self::$bypass = false;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runWithoutScope(callable $callback): mixed
    {
        $previous = self::$bypass;
        self::$bypass = true;

        try {
            return $callback();
        } finally {
            self::$bypass = $previous;
        }
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = self::$tenant;
        self::$tenant = $tenant;

        try {
            return $callback();
        } finally {
            self::$tenant = $previousTenant;
        }
    }
}
