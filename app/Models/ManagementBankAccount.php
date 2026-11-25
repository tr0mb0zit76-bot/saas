<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManagementBankAccount extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'bank_name',
        'account_number',
        'account_mask',
        'currency',
        'opening_balance',
        'opening_balance_as_of',
        'opening_balance_source',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:2',
            'opening_balance_as_of' => 'date',
        ];
    }

    /**
     * @return HasMany<ManagementStatementImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(ManagementStatementImport::class, 'bank_account_id');
    }

    /**
     * @return HasMany<ManagementStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ManagementStatementLine::class, 'bank_account_id');
    }

    public static function consolidated(): self
    {
        return self::query()->updateOrCreate(
            ['account_number' => 'CONSOLIDATED'],
            [
                'bank_name' => 'Сводная выписка',
                'account_mask' => 'без счёта',
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 5,
            ],
        );
    }

    public static function cash(): self
    {
        return self::query()->updateOrCreate(
            ['account_number' => 'CASH'],
            [
                'bank_name' => 'Касса (наличные)',
                'account_mask' => 'нал',
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );
    }

    public static function corporateCard(): self
    {
        return self::query()->updateOrCreate(
            ['account_number' => 'CORP_CARD'],
            [
                'bank_name' => 'Корпоративная карта',
                'account_mask' => 'карта',
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );
    }

    public static function ensureManualInstruments(): void
    {
        if (! self::query()->where('account_number', 'CASH')->exists()) {
            self::cash();
        }

        if (! self::query()->where('account_number', 'CORP_CARD')->exists()) {
            self::corporateCard();
        }
    }

    public static function instrumentFor(?string $accountNumber): string
    {
        return match ((string) $accountNumber) {
            'CASH' => 'cash',
            'CORP_CARD' => 'corp_card',
            'CONSOLIDATED' => 'consolidated',
            default => 'bank',
        };
    }
}
