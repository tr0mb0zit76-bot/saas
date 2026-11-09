<?php

namespace App\Support;

final class LeadSource
{
    /**
     * @var array<string, string>
     */
    public const SOURCES = [
        'inbound' => 'Входящий',
        'outbound' => 'Исходящий',
        'referral' => 'Рекомендация',
        'website' => 'Сайт',
        'existing_customer' => 'Действующий клиент',
        'base_reprocessing' => 'Повторная обработка базы',
        'other' => 'Другое',
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::SOURCES);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::SOURCES),
            array_values(self::SOURCES),
        );
    }

    public static function label(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Traklo cabinet requests store source as traklo_customer_request[ · contact]; UI treats them as inbound.
        if ($value === 'traklo_customer_request' || str_starts_with($value, 'traklo_customer_request · ')) {
            return self::SOURCES['inbound'];
        }

        return self::SOURCES[$value] ?? $value;
    }
}
