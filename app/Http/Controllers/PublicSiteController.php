<?php

namespace App\Http\Controllers;

use App\Support\SaasFeatureCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    private const COOKIE_NAME = 'public_site_locale';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['ru', 'en', 'cn'];

    /**
     * Каноническая русская локаль: resources/locales/public/ru.json (не затирается типичным rsync/clean public/).
     * Дубли: public/locales/ru.json (если нужен прямой доступ по URL), publicSiteRuFallbacks.json во фронте.
     *
     * @return list<string>
     */
    protected function translationPathsForLocale(string $locale): array
    {
        return match ($locale) {
            'ru' => [
                public_path('locales/ru.json'),
                public_path('assets/locales/ru.json'),
                public_path('change/locales/ru.json'),
                resource_path('locales/public/ru.json'),
                public_path('locales/en.json'),
            ],
            'cn' => [
                public_path('locales/cn.json'),
                public_path('locales/en.json'),
            ],
            default => [
                public_path('locales/en.json'),
            ],
        };
    }

    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        $normalized = strtolower($locale);
        if (! in_array($normalized, self::SUPPORTED_LOCALES, true)) {
            abort(404);
        }

        $fallback = \Route::has('public.home') ? route('public.home') : '/';

        return redirect()->back(fallback: $fallback)->withCookie(cookie(
            self::COOKIE_NAME,
            $normalized,
            self::COOKIE_MINUTES,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    protected function resolveCrmLoginUrl(): string
    {
        $crmHost = strtolower(trim((string) config('app.crm_domain')));
        if ($crmHost === '') {
            $crmHost = strtolower(trim((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost')));
        }

        /** @var list<string> $showcaseHosts */
        $showcaseHosts = config('app.showcase_hosts', []);
        $showcaseHost = strtolower(trim((string) ($showcaseHosts[0] ?? '')));

        if ($showcaseHost !== '' && $showcaseHost === $crmHost) {
            return '/login';
        }

        if (strtolower(trim((string) request()->getHost())) === $crmHost) {
            return '/login';
        }

        $scheme = request()->isSecure() ? 'https' : 'http';

        return sprintf('%s://%s/login', $scheme, $crmHost);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedProps(): array
    {
        $locale = $this->resolvePublicLocale();
        $translations = [];

        foreach ($this->translationPathsForLocale($locale) as $translationsPath) {
            if (! is_file($translationsPath)) {
                continue;
            }

            $decodedTranslations = json_decode((string) file_get_contents($translationsPath), true);

            if (is_array($decodedTranslations)) {
                $translations = $decodedTranslations;
                break;
            }
        }

        return [
            'canLogin' => \Route::has('login'),
            'canRegister' => \Route::has('register'),
            'publicSite' => [
                'texts' => $translations,
                'crm_login_url' => $this->resolveCrmLoginUrl(),
                'active_locale' => $locale,
                'available_locales' => [
                    ['code' => 'ru', 'label' => 'RU'],
                    ['code' => 'en', 'label' => 'EN'],
                    ['code' => 'cn', 'label' => '中文'],
                ],
                'checko_company_url' => (string) config('app.showcase_checko_company_url', ''),
                'sla_documents' => $this->slaDocumentsForFrontend(),
                'traklo_apk_url' => $this->resolveTrakloApkUrl(),
            ],
        ];
    }

    protected function resolveTrakloApkUrl(): string
    {
        $configured = trim((string) config('external_users.apk_url', '/downloads/traklo.apk'));

        if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
            return $configured;
        }

        $path = str_starts_with($configured, '/') ? $configured : '/'.$configured;

        if (in_array($path, ['/downloads/traklo', '/downloads/traklo/'], true)) {
            $path = '/downloads/traklo.apk';
        }

        $crmHost = trim((string) config('app.crm_domain'));
        if ($crmHost === '') {
            $crmHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        }

        $scheme = request()->isSecure() ? 'https' : 'http';

        return sprintf('%s://%s%s', $scheme, $crmHost, $path);
    }

    /**
     * @return list<array{id: string, panel: string, label: string, preview_url: string|null}>
     */
    protected function slaDocumentsForFrontend(): array
    {
        /** @var array<string, array{panel?: string, label?: string}> $catalog */
        $catalog = config('showcase.sla_documents', []);
        $documents = [];

        foreach ($catalog as $id => $entry) {
            $panel = (string) ($entry['panel'] ?? '');
            if ($panel === '') {
                continue;
            }

            $documents[] = [
                'id' => (string) $id,
                'panel' => $panel,
                'label' => (string) ($entry['label'] ?? $id),
                'preview_url' => \Route::has('public.sla.document')
                    ? route('public.sla.document', ['document' => $id])
                    : null,
            ];
        }

        return $documents;
    }

    protected function resolvePublicLocale(): string
    {
        $fromCookie = request()->cookie(self::COOKIE_NAME);
        if (is_string($fromCookie)) {
            $candidate = strtolower(trim($fromCookie));
            if (in_array($candidate, self::SUPPORTED_LOCALES, true)) {
                return $candidate;
            }
        }

        return 'ru';
    }

    public function home(): Response
    {
        if (config('showcase.mode') === 'traklo_pro') {
            return Inertia::render('Public/TrakloLanding', $this->trakloProProps());
        }

        return Inertia::render('Welcome', $this->sharedProps());
    }

    /**
     * @return array<string, mixed>
     */
    protected function trakloProProps(): array
    {
        $texts = [];
        $path = resource_path('locales/public/traklo-pro.ru.json');

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $texts = $decoded;
            }
        }

        $crmLoginUrl = $this->resolveCrmLoginUrl();

        $planSummaries = SaasFeatureCatalog::planSummaries();
        $plans = [];

        foreach ($planSummaries as $summary) {
            $key = (string) ($summary['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $limits = is_array($summary['limits'] ?? null) ? $summary['limits'] : [];
            $users = $limits['users'] ?? null;
            $usersLabel = $users === null
                ? (string) ($texts['plan_'.$key.'_users'] ?? 'без лимита по договору')
                : (string) ($texts['plan_'.$key.'_users'] ?? ('до '.$users.' пользователей'));

            $plans[] = [
                'key' => $key,
                'label' => (string) ($texts['plan_'.$key] ?? $summary['label'] ?? ucfirst($key)),
                'users' => $usersLabel,
                'limits' => $limits,
                'featured' => $key === 'pro',
            ];
        }

        $crmHost = trim((string) config('app.crm_domain'));
        if ($crmHost === '') {
            $crmHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        }
        $crmScheme = request()->isSecure() ? 'https' : 'http';

        return [
            'canLogin' => \Route::has('login'),
            'texts' => $texts,
            'crmLoginUrl' => $crmLoginUrl,
            'demoSignupUrl' => config('saas.demo_signup_enabled', false)
                ? sprintf('%s://%s/demo/signup', $crmScheme, $crmHost)
                : null,
            'plans' => $plans,
            'planMatrix' => SaasFeatureCatalog::planMatrix(),
            'publicSite' => [
                'texts' => $texts,
                'crm_login_url' => $crmLoginUrl,
            ],
        ];
    }

    public function about(): Response
    {
        return Inertia::render('Public/About', $this->sharedProps());
    }

    public function services(): Response
    {
        return Inertia::render('Public/Services', $this->sharedProps());
    }

    public function cases(): Response
    {
        return Inertia::render('Public/Cases', $this->sharedProps());
    }

    public function contacts(): Response
    {
        return Inertia::render('Public/Contacts', $this->sharedProps());
    }

    public function sla(): Response
    {
        return Inertia::render('Public/Sla', $this->sharedProps());
    }
}
