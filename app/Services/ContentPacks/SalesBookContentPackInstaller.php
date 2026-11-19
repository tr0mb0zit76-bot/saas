<?php

namespace App\Services\ContentPacks;

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use App\Models\Tenant;
use App\Support\TenantContext;
use InvalidArgumentException;

final class SalesBookContentPackInstaller
{
    public const PACK_CRM_USER_GUIDE = 'crm-user-guide';

    public const PACK_ADMIN_GUIDE = 'admin-guide';

    public const PACK_SALES_PLAYBOOK = 'sales-playbook';

    /**
     * @return list<string>
     */
    public static function knownPacks(): array
    {
        return [
            self::PACK_CRM_USER_GUIDE,
            self::PACK_ADMIN_GUIDE,
            self::PACK_SALES_PLAYBOOK,
        ];
    }

    /**
     * @return list<string>
     */
    public static function classAPacks(): array
    {
        return self::knownPacks();
    }

    /**
     * @return list<string>
     */
    public static function classBDefaultPacks(): array
    {
        return [self::PACK_CRM_USER_GUIDE];
    }

    public function install(Tenant $tenant, string $packSlug, bool $reset = false): int
    {
        if (! in_array($packSlug, self::knownPacks(), true)) {
            throw new InvalidArgumentException("Unknown sales book pack [{$packSlug}].");
        }

        $pages = $this->pagesFor($packSlug);
        $createdOrUpdated = 0;

        TenantContext::runAs($tenant, function () use ($tenant, $packSlug, $pages, $reset, &$createdOrUpdated): void {
            $root = $this->upsertPage(
                $tenant,
                $packSlug,
                $packSlug,
                $pages['root']['title'],
                $pages['root']['markdown'],
                null,
                0,
                $reset,
            );
            $createdOrUpdated++;

            foreach ($pages['children'] as $index => $child) {
                $this->upsertPage(
                    $tenant,
                    $packSlug,
                    (string) $child['key'],
                    (string) $child['title'],
                    (string) $child['markdown'],
                    $root->id,
                    $index + 1,
                    $reset,
                );
                $createdOrUpdated++;
            }

            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $installed = data_get($settings, 'content_packs.sales_book', []);
            $installed = is_array($installed) ? array_values(array_filter($installed, 'is_string')) : [];
            if (! in_array($packSlug, $installed, true)) {
                $installed[] = $packSlug;
            }
            data_set($settings, 'content_packs.sales_book', $installed);
            $tenant->forceFill(['settings' => $settings])->save();
        });

        return $createdOrUpdated;
    }

    /**
     * @param  list<string>  $packSlugs
     */
    public function installMany(Tenant $tenant, array $packSlugs, bool $reset = false): void
    {
        foreach ($packSlugs as $slug) {
            $this->install($tenant, $slug, $reset);
        }
    }

    /**
     * @return array{root: array{title: string, markdown: string}, children: list<array{key: string, title: string, markdown: string}>}
     */
    private function pagesFor(string $packSlug): array
    {
        /** @var array{root: array{title: string, markdown: string}, children: list<array{key: string, title: string, markdown: string}>} */
        $pages = require database_path("content-packs/sales_book/{$packSlug}/pages.php");

        return $pages;
    }

    private function upsertPage(
        Tenant $tenant,
        string $packSlug,
        string $pageKey,
        string $title,
        string $markdown,
        ?int $parentId,
        int $sortOrder,
        bool $reset,
    ): SalesBookArticle {
        $packTag = "pack:{$packSlug}";
        $pageTag = "pack_page:{$pageKey}";

        $existing = SalesBookArticle::query()
            ->where('tenant_id', $tenant->id)
            ->whereJsonContains('tags', $packTag)
            ->whereJsonContains('tags', $pageTag)
            ->first();

        if ($existing !== null) {
            if ($reset) {
                $existing->update([
                    'title' => $title,
                    'markdown_content' => $markdown,
                    'parent_id' => $parentId,
                    'sort_order' => $sortOrder,
                    'status' => SalesBookArticleStatus::Published,
                ]);
            }

            return $existing->refresh();
        }

        return SalesBookArticle::query()->create([
            'tenant_id' => $tenant->id,
            'parent_id' => $parentId,
            'title' => $title,
            'markdown_content' => $markdown,
            'sort_order' => $sortOrder,
            'status' => SalesBookArticleStatus::Published,
            'tags' => [$packTag, $pageTag],
            'content_format' => 'markdown',
        ]);
    }
}
