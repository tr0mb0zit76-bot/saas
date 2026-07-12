# Анимация логина Traklo: варианты векторизации / покадровой сборки
#
# Контекст: `public/downloads/traklo-icon.png` (1024²), поля на белых полосах.
# Цель: плавное движение грузовика без «кривого» CSS-спрайта.

## Варианты (от лучшего к худшему для UI)

### 1. SVG trace + motion path (рекомендую)
1. Inkscape / Illustrator: Trace Bitmap → ручная чистка слоёв.
2. Слои: `bubble`, `route`, `pin-a`, `pin-b`, `truck`, _(без белых полос — их заменяют inputs)_.
3. Кадр 0 = SVG без `truck` (или truck opacity 0).
4. В браузере: `offset-path` / GSAP MotionPath по path маршрута.
Плюсы: резко на retina, маленький вес, без GIF. Минусы: 1–2 часа ручной чистки path.

### 2. Lottie / Rive
After Effects + Bodymovin, или Rive Editor.
Плюсы: продакшен-качество, easing, интерактив. Минусы: нужен дизайн-инструмент.

### 3. Покадровая сборка (ваша идея) → лучше APNG/WebP, не GIF
Пайплайн:
1. `traklo-icon-empty.png` — иконка без грузовика (залить синим bbox).
2. `traklo-truck-sprite.png` — чистый грузовик (уже есть скрипт).
3. Для t=0..N: композит truck по Bezier маршрута → `frames/frame-00.png` …
4. Сборка:
   - `ffmpeg -framerate 30 -i frame-%02d.png -plays 0 login-truck.apng`
   - или WebP animated
   - GIF только если очень надо (палитра 256 → грязь на синем градиенте).
Плюсы: полный контроль «как в ролике». Минусы: вес, сложнее править путь.

Скрипты-заготовки в репо:
- `scripts/measure-traklo-icon-bars.php`
- `scripts/extract-traklo-truck-sprite.php`
- следующий шаг: `scripts/render-traklo-login-frames.php` (GD композит кадров)

### 4. CSS sprite sheet из тех же кадров
Те же PNG-кадры → одна лента → `steps()` animation.
Плюсы: без GIF. Минусы: вес PNG-ленты.

## Что не стоит
- Авто-SVG «в один клик» без чистки (получится «черепаха»).
- GIF на градиентном bubble (banding).
- Ехать растровым crop без empty-кадра (двойной грузовик / синее пятно).

## Практичный план для saas.local

**Сделано в репо:**
1. `scripts/render-traklo-login-frames.php` — empty icon + 24 WebP-кадра (truck по Bezier) + `manifest.json`
2. `TrakloLoginScene.vue` — проигрывает кадры через `requestAnimationFrame`, затем показывает оригинальный `traklo-icon.png`
3. Поля Email/Пароль — прозрачные overlays на белых полосах; bubble ~28–32rem

Пересобрать кадры:
```powershell
php scripts/render-traklo-login-frames.php --frames=24 --size=720
```

Дальше (опционально): ручной SVG trace или Rive для идеальной траектории без растрового wipe.
