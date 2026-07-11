<?php

namespace App\Services\PrintForm;

use App\Services\PrintFormVariableCatalog;
use App\Support\PrintFormImageOverlayPlaceholders;
use App\Support\PrintFormPlaceholderPathResolver;

/**
 * Формализованный справочник плейсхолдеров для настройки DOCX-шаблонов арендатором.
 */
class PrintFormPlaceholderGuide
{
    public function __construct(
        private readonly PrintFormVariableCatalog $variableCatalog,
        private readonly PrintFormPlaceholderPathResolver $pathResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $pathLabels = $this->variableCatalog->pathLabelIndex();

        return [
            'intro' => 'В DOCX используйте макросы ${...} или {{...}}. После загрузки шаблона CRM сопоставит их с полями заказа/лида. '
                .'Можно использовать готовые короткие имена (inn, nomer_zayavki) или полные пути (customer.inn).',
            'usage_steps' => [
                'Скачайте или откройте этот справочник и выберите нужные поля.',
                'В Word вставьте макросы в фигурных скобках: ${inn}, ${order.order_number}.',
                'Загрузите DOCX в Настройки → Шаблоны.',
                'На вкладке «Плейсхолдеры» проверьте сопоставления и при необходимости поправьте источник данных.',
                'Сгенерируйте тестовый документ по заказу или лиду.',
            ],
            'entity_types' => [
                [
                    'key' => 'order',
                    'label' => 'Заказ',
                    'groups' => $this->variableCatalog->groupedOrderOptions(),
                ],
                [
                    'key' => 'lead',
                    'label' => 'Лид / КП',
                    'groups' => $this->variableCatalog->groupedLeadOptions(),
                ],
            ],
            'legacy_aliases' => [
                [
                    'key' => 'legacy',
                    'label' => 'Короткие имена (совместимость с v5)',
                    'items' => array_values(array_filter(
                        $this->pathResolver->legacyAliasCatalog($pathLabels),
                        static fn (array $row): bool => $row['family'] === 'legacy',
                    )),
                ],
                [
                    'key' => 'cp',
                    'label' => 'Префикс cp_ — заказчик',
                    'items' => array_values(array_filter(
                        $this->pathResolver->legacyAliasCatalog($pathLabels),
                        static fn (array $row): bool => $row['family'] === 'cp',
                    )),
                ],
                [
                    'key' => 'dp',
                    'label' => 'Префикс dp_ — перевозчик',
                    'items' => array_values(array_filter(
                        $this->pathResolver->legacyAliasCatalog($pathLabels),
                        static fn (array $row): bool => $row['family'] === 'dp',
                    )),
                ],
                [
                    'key' => 'lp',
                    'label' => 'Префикс lp_ — своя компания',
                    'items' => array_values(array_filter(
                        $this->pathResolver->legacyAliasCatalog($pathLabels),
                        static fn (array $row): bool => $row['family'] === 'lp',
                    )),
                ],
            ],
            'special_macros' => [
                [
                    'macro' => '${cargo_row_name}',
                    'description' => 'Таблица грузов: якорь cloneRow — CRM размножит строки по позициям груза.',
                ],
                [
                    'macro' => '${route_point_row_address}',
                    'description' => 'Таблица точек маршрута: якорь cloneRow для погрузок/выгрузок.',
                ],
                [
                    'macro' => '${cp_basic_terms_row_text}',
                    'description' => 'Базовые условия заказчика: таблица пунктов договора-заявки (cloneRow).',
                ],
                [
                    'macro' => '${dp_basic_terms_row_text}',
                    'description' => 'Базовые условия перевозчика: таблица пунктов (cloneRow).',
                ],
                [
                    'macro' => '${'.PrintFormImageOverlayPlaceholders::DEFAULT_SIGNATURE.'}',
                    'description' => 'Изображение подписи (PNG), настраивается в блоке «Подпись и печать».',
                ],
                [
                    'macro' => '${'.PrintFormImageOverlayPlaceholders::DEFAULT_STAMP.'}',
                    'description' => 'Изображение печати (PNG), настраивается в блоке «Подпись и печать».',
                ],
                [
                    'macro' => '${document_verification_qr}',
                    'description' => 'QR-код проверки подлинности финального документа.',
                ],
            ],
        ];
    }
}
