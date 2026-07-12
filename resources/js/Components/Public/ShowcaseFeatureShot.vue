<script setup>
/**
 * Tilted product UI mock for showcase (placeholder until real screenshots).
 * ponytail: CSS mocks — swap for real cabinet shots when ready; keep variant keys stable.
 * variant: leads | orders | payments | print | documents | scripts | salesbook | howmuchfits | payroll | mobile | rbac | ai | accounting | budgeting | companyplanning | aianalytics | loadboard | fleet | disposition | integrations | customdomain
 */
defineProps({
    variant: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
    tilt: {
        type: String,
        default: 'right',
    },
});

const leadCols = [
    { title: 'Новые', cards: ['ООО Север', 'ИП Орлов'] },
    { title: 'Переговоры', cards: ['Логистик+', 'Транс-Вест'] },
    { title: 'Сделка', cards: ['Автоальянс'] },
];

const orderSteps = ['Маршрут', 'Груз', 'Финансы', 'Документы'];

const payRows = [
    { label: 'Аванс', date: '12.07', amount: '60 000 ₽', due: false },
    { label: 'По факту', date: '18.07', amount: '88 000 ₽', due: true },
];

const roles = [
    { name: 'Менеджер', scope: 'свои' },
    { name: 'РОП', scope: 'отдел' },
    { name: 'Админ', scope: 'все' },
];

const docRows = [
    { name: 'Заявка №1042', meta: 'сегодня' },
    { name: 'Договор-заявка', meta: 'вчера' },
    { name: 'Скан ТТН', meta: '18.07' },
];

const scriptSteps = ['Приветствие', 'Потребность', 'Возражение', 'Закрытие'];

const salesbookRows = [
    { name: 'Возражения по ставке', meta: '12 статей' },
    { name: 'Тент / негабарит', meta: '8 статей' },
    { name: 'Ввод нового менеджера', meta: '5 статей' },
];

const fitItems = [
    { label: 'Паллеты', value: '18 / 22' },
    { label: 'Вес', value: '16,4 т' },
    { label: 'Кузов', value: '13,6 м' },
];

const payRules = [
    { name: 'Менеджер', rule: '8% от маржи' },
    { name: 'РОП', rule: '2% + порог' },
    { name: 'Диспетчер', rule: 'фикс / рейс' },
];

const boardCols = [
    { title: 'Нужна машина', items: ['Москва → Тверь', 'Казань → Уфа'] },
    { title: 'В работе', items: ['Смоленск · тент'] },
    { title: 'Закрыто', items: ['Рейс #1042'] },
];

const fleetRows = [
    { name: 'А123ВС 77', meta: 'свободна' },
    { name: 'В456ОР 50', meta: 'в рейсе' },
    { name: 'С789КТ 178', meta: 'ТО' },
];

const dispositionRows = [
    { name: 'Утро', meta: '12 / 14 на линии' },
    { name: 'Вечер', meta: '3 риска срыва' },
    { name: 'Смена', meta: 'передано' },
];

const integrationRows = [
    { name: 'Выгрузки заказов', meta: 'включено' },
    { name: 'Справочники', meta: 'по договору' },
    { name: 'Обмен с учётом', meta: 'настройка' },
];
</script>

<template>
    <div
        class="showcase-shot"
        :class="tilt === 'left' ? 'showcase-shot--left' : 'showcase-shot--right'"
    >
        <div class="showcase-shot__glow" aria-hidden="true" />
        <div class="showcase-shot__frame">
            <div class="showcase-shot__chrome">
                <span class="showcase-shot__dot" />
                <span class="showcase-shot__dot" />
                <span class="showcase-shot__dot" />
                <span v-if="label" class="showcase-shot__url">{{ label }}</span>
            </div>

            <div class="showcase-shot__screen">
                <template v-if="variant === 'leads'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Воронка</span>
                        <span class="shot-muted">12 активных</span>
                    </div>
                    <div class="shot-kanban">
                        <div v-for="col in leadCols" :key="col.title" class="shot-col">
                            <div class="shot-col__title">{{ col.title }}</div>
                            <div v-for="card in col.cards" :key="card" class="shot-card">{{ card }}</div>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'orders'">
                    <div class="shot-steps">
                        <span
                            v-for="(s, i) in orderSteps"
                            :key="s"
                            class="shot-step"
                            :class="{ 'shot-step--on': i === 1 }"
                        >{{ s }}</span>
                    </div>
                    <div class="shot-map">
                        <div class="shot-pin shot-pin--a" />
                        <div class="shot-route" />
                        <div class="shot-pin shot-pin--b" />
                    </div>
                    <div class="shot-grid-2">
                        <div class="shot-field"><span>Погрузка</span><strong>Москва</strong></div>
                        <div class="shot-field"><span>Выгрузка</span><strong>Смоленск</strong></div>
                        <div class="shot-field"><span>Груз</span><strong>24 т · тент</strong></div>
                        <div class="shot-field"><span>Ставка</span><strong>148 000 ₽</strong></div>
                    </div>
                </template>

                <template v-else-if="variant === 'payments'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill shot-pill--amber">График оплат</span>
                        <span class="shot-muted">Заказ #1042</span>
                    </div>
                    <div class="shot-table">
                        <div v-for="row in payRows" :key="row.label" class="shot-tr">
                            <span>{{ row.label }}</span>
                            <span>{{ row.date }}</span>
                            <strong :class="row.due ? 'is-due' : ''">{{ row.amount }}</strong>
                        </div>
                    </div>
                    <div class="shot-bar">
                        <div class="shot-bar__fill" />
                    </div>
                </template>

                <template v-else-if="variant === 'print'">
                    <div class="shot-doc">
                        <div class="shot-doc__meta">Договор-заявка · документ → PDF</div>
                        <div class="shot-doc__line" />
                        <div class="shot-doc__line shot-doc__line--short" />
                        <div class="shot-doc__block" />
                        <div class="shot-doc__qr" />
                    </div>
                </template>

                <template v-else-if="variant === 'documents'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Реестр</span>
                        <span class="shot-muted">заказ #1042</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="doc in docRows" :key="doc.name" class="shot-role">
                            <strong>{{ doc.name }}</strong>
                            <span>{{ doc.meta }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'scripts'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Скрипт · тренажёр</span>
                        <span class="shot-muted">самообучение</span>
                    </div>
                    <div class="shot-steps">
                        <span
                            v-for="(s, i) in scriptSteps"
                            :key="s"
                            class="shot-step"
                            :class="{ 'shot-step--on': i === 2 }"
                        >{{ s }}</span>
                    </div>
                    <div class="shot-ai">
                        <div class="shot-ai__reply">
                            Клиент: «Дорого». Подсказка усилилась после 14 прохождений: сравнить ставку и окно погрузки.
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'salesbook'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Книга продаж</span>
                        <span class="shot-muted">25 материалов</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="row in salesbookRows" :key="row.name" class="shot-role">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.meta }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'howmuchfits'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill shot-pill--amber">Сколько влезет</span>
                        <span class="shot-muted">тент 13,6</span>
                    </div>
                    <div class="shot-fit">
                        <div class="shot-fit__truck">
                            <div class="shot-fit__box shot-fit__box--a" />
                            <div class="shot-fit__box shot-fit__box--b" />
                            <div class="shot-fit__box shot-fit__box--c" />
                        </div>
                        <div class="shot-grid-2">
                            <div v-for="item in fitItems" :key="item.label" class="shot-field">
                                <span>{{ item.label }}</span>
                                <strong>{{ item.value }}</strong>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'payroll'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Зарплата</span>
                        <span class="shot-muted">июль</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="row in payRules" :key="row.name" class="shot-role">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.rule }}</span>
                        </div>
                    </div>
                    <div class="shot-bar mt-3">
                        <div class="shot-bar__fill" style="width: 74%" />
                    </div>
                </template>

                <template v-else-if="variant === 'mobile'">
                    <div class="shot-phone">
                        <div class="shot-phone__notch" />
                        <div class="shot-phone__body">
                            <div class="shot-pill">Traklo</div>
                            <div class="shot-card mt-2">Заказ #1042 · в пути</div>
                            <div class="shot-card">Задача: позвонить клиенту</div>
                            <div class="shot-card">Маршрут: Москва → Смоленск</div>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'rbac'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Роли</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="role in roles" :key="role.name" class="shot-role">
                            <strong>{{ role.name }}</strong>
                            <span>{{ role.scope }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'accounting'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Управленка</span>
                        <span class="shot-muted">июль</span>
                    </div>
                    <div class="shot-grid-2">
                        <div class="shot-field"><span>Маржа</span><strong>2,4 млн ₽</strong></div>
                        <div class="shot-field"><span>Расходы</span><strong>1,1 млн ₽</strong></div>
                        <div class="shot-field"><span>Разнесено</span><strong>86%</strong></div>
                        <div class="shot-field"><span>В очереди</span><strong>14 строк</strong></div>
                    </div>
                    <div class="shot-bar mt-3">
                        <div class="shot-bar__fill" style="width: 86%" />
                    </div>
                </template>

                <template v-else-if="variant === 'budgeting'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Бюджет</span>
                        <span class="shot-muted">сценарий A</span>
                    </div>
                    <div class="shot-grid-2">
                        <div class="shot-field"><span>План продаж</span><strong>18 млн ₽</strong></div>
                        <div class="shot-field"><span>OPEX</span><strong>4,2 млн ₽</strong></div>
                        <div class="shot-field"><span>Факт</span><strong>72%</strong></div>
                        <div class="shot-field"><span>Отклонение</span><strong>−3%</strong></div>
                    </div>
                </template>

                <template v-else-if="variant === 'companyplanning'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">План компании</span>
                        <span class="shot-muted">Q3</span>
                    </div>
                    <div class="shot-roles">
                        <div class="shot-role"><strong>Найм логистов</strong><span>этап 2/4</span></div>
                        <div class="shot-role"><strong>Новый склад</strong><span>срок 28.08</span></div>
                        <div class="shot-role"><strong>Скрипты Pro</strong><span>в работе</span></div>
                    </div>
                </template>

                <template v-else-if="variant === 'aianalytics'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Аналитика ИИ</span>
                        <span class="shot-muted">7 дней</span>
                    </div>
                    <div class="shot-grid-2">
                        <div class="shot-field"><span>Запросы</span><strong>186</strong></div>
                        <div class="shot-field"><span>Пробелы знаний</span><strong>9</strong></div>
                        <div class="shot-field"><span>Слабые скрипты</span><strong>3</strong></div>
                        <div class="shot-field"><span>Зацикливания</span><strong>12</strong></div>
                    </div>
                </template>

                <template v-else-if="variant === 'loadboard'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill shot-pill--amber">Биржа</span>
                        <span class="shot-muted">сегодня</span>
                    </div>
                    <div class="shot-kanban">
                        <div v-for="col in boardCols" :key="col.title" class="shot-col">
                            <div class="shot-col__title">{{ col.title }}</div>
                            <div v-for="item in col.items" :key="item" class="shot-card">{{ item }}</div>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'fleet'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Автопарк</span>
                        <span class="shot-muted">24 машины</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="row in fleetRows" :key="row.name" class="shot-role">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.meta }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'disposition'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Диспозиция</span>
                        <span class="shot-muted">смена</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="row in dispositionRows" :key="row.name" class="shot-role">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.meta }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'integrations'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Интеграции</span>
                        <span class="shot-muted">по договору</span>
                    </div>
                    <div class="shot-roles">
                        <div v-for="row in integrationRows" :key="row.name" class="shot-role">
                            <strong>{{ row.name }}</strong>
                            <span>{{ row.meta }}</span>
                        </div>
                    </div>
                </template>

                <template v-else-if="variant === 'customdomain'">
                    <div class="shot-row shot-row--head">
                        <span class="shot-pill">Свой адрес</span>
                    </div>
                    <div class="shot-ai">
                        <div class="shot-ai__reply">
                            crm.avtoaliyans.ru — постоянная точка входа для команды и партнёров.
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="shot-ai">
                        <div class="shot-ai__prompt">Найти перевозчика на Смоленск завтра…</div>
                        <div class="shot-ai__reply">
                            3 варианта по ставке и рейтингу. Открыть заказ #1042?
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.showcase-shot {
    position: relative;
    perspective: 1400px;
    padding: 1.25rem 0.5rem 1.5rem;
}

.showcase-shot__glow {
    position: absolute;
    inset: 10% 5% 5%;
    border-radius: 2rem;
    background: radial-gradient(circle at 40% 30%, rgb(59 130 246 / 0.35), transparent 65%);
    filter: blur(28px);
    z-index: 0;
}

.showcase-shot__frame {
    position: relative;
    z-index: 1;
    overflow: hidden;
    border-radius: 1rem;
    border: 1px solid rgb(255 255 255 / 0.12);
    background: linear-gradient(165deg, #1e293b 0%, #0f172a 55%, #0b1220 100%);
    box-shadow:
        0 30px 60px -20px rgb(0 0 0 / 0.55),
        0 0 0 1px rgb(255 255 255 / 0.04) inset;
    transform-style: preserve-3d;
    transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

.showcase-shot--right .showcase-shot__frame {
    transform: rotateY(-14deg) rotateX(6deg) rotateZ(1.5deg);
}

.showcase-shot--left .showcase-shot__frame {
    transform: rotateY(14deg) rotateX(6deg) rotateZ(-1.5deg);
}

.showcase-shot:hover .showcase-shot__frame {
    transform: rotateY(-4deg) rotateX(2deg) rotateZ(0deg) translateY(-4px);
}

.showcase-shot--left:hover .showcase-shot__frame {
    transform: rotateY(4deg) rotateX(2deg) rotateZ(0deg) translateY(-4px);
}

.showcase-shot__chrome {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid rgb(255 255 255 / 0.06);
    background: rgb(15 23 42 / 0.85);
}

.showcase-shot__dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 9999px;
    background: rgb(148 163 184 / 0.45);
}

.showcase-shot__dot:nth-child(1) { background: #f87171; }
.showcase-shot__dot:nth-child(2) { background: #fbbf24; }
.showcase-shot__dot:nth-child(3) { background: #34d399; }

.showcase-shot__url {
    margin-left: 0.6rem;
    font-size: 0.65rem;
    color: rgb(148 163 184 / 0.9);
    letter-spacing: 0.02em;
}

.showcase-shot__screen {
    min-height: 13.5rem;
    padding: 0.9rem;
}

.shot-row--head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.shot-pill {
    display: inline-flex;
    border-radius: 9999px;
    background: rgb(37 99 235 / 0.25);
    color: #93c5fd;
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
}

.shot-pill--amber {
    background: rgb(245 158 11 / 0.2);
    color: #fcd34d;
}

.shot-muted {
    font-size: 0.65rem;
    color: #64748b;
}

.shot-kanban {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
}

.shot-col {
    border-radius: 0.55rem;
    background: rgb(255 255 255 / 0.03);
    padding: 0.4rem;
    border: 1px solid rgb(255 255 255 / 0.05);
}

.shot-col__title {
    font-size: 0.6rem;
    color: #94a3b8;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.shot-card {
    border-radius: 0.4rem;
    background: rgb(30 41 59 / 0.95);
    border: 1px solid rgb(255 255 255 / 0.06);
    color: #e2e8f0;
    font-size: 0.65rem;
    padding: 0.4rem 0.45rem;
    margin-bottom: 0.3rem;
}

.shot-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 0.75rem;
}

.shot-step {
    font-size: 0.6rem;
    color: #64748b;
    border: 1px solid rgb(255 255 255 / 0.08);
    border-radius: 9999px;
    padding: 0.2rem 0.5rem;
}

.shot-step--on {
    color: #bfdbfe;
    border-color: rgb(59 130 246 / 0.45);
    background: rgb(37 99 235 / 0.2);
}

.shot-map {
    position: relative;
    height: 4.2rem;
    margin-bottom: 0.75rem;
    border-radius: 0.65rem;
    background:
        radial-gradient(circle at 20% 70%, rgb(37 99 235 / 0.25), transparent 40%),
        linear-gradient(180deg, #122033, #0b1524);
    border: 1px solid rgb(255 255 255 / 0.06);
    overflow: hidden;
}

.shot-route {
    position: absolute;
    left: 18%;
    right: 18%;
    top: 55%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #93c5fd, transparent);
    transform: rotate(-8deg);
    box-shadow: 0 0 12px rgb(147 197 253 / 0.6);
}

.shot-pin {
    position: absolute;
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 9999px 9999px 9999px 0;
    background: #fff;
    transform: rotate(-45deg);
}

.shot-pin--a { left: 16%; top: 48%; }
.shot-pin--b { right: 16%; top: 28%; }

.shot-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
}

.shot-field {
    border-radius: 0.5rem;
    background: rgb(255 255 255 / 0.03);
    border: 1px solid rgb(255 255 255 / 0.06);
    padding: 0.4rem 0.5rem;
}

.shot-field span {
    display: block;
    font-size: 0.55rem;
    color: #64748b;
}

.shot-field strong {
    font-size: 0.7rem;
    color: #e2e8f0;
    font-weight: 600;
}

.shot-table {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-bottom: 0.7rem;
}

.shot-tr {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 1fr;
    gap: 0.4rem;
    align-items: center;
    font-size: 0.68rem;
    color: #cbd5e1;
    padding: 0.4rem 0.5rem;
    border-radius: 0.45rem;
    background: rgb(255 255 255 / 0.03);
    border: 1px solid rgb(255 255 255 / 0.05);
}

.shot-tr .is-due {
    color: #fcd34d;
}

.shot-bar {
    height: 0.4rem;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.06);
    overflow: hidden;
}

.shot-bar__fill {
    width: 62%;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #2563eb, #38bdf8);
}

.shot-doc {
    position: relative;
    min-height: 11rem;
    border-radius: 0.65rem;
    background: #f8fafc;
    color: #0f172a;
    padding: 0.85rem;
}

.shot-doc__meta {
    font-size: 0.65rem;
    font-weight: 600;
    margin-bottom: 0.7rem;
    color: #334155;
}

.shot-doc__line {
    height: 0.35rem;
    width: 88%;
    border-radius: 9999px;
    background: #cbd5e1;
    margin-bottom: 0.4rem;
}

.shot-doc__line--short { width: 55%; }

.shot-doc__block {
    margin-top: 0.8rem;
    height: 3.5rem;
    border-radius: 0.4rem;
    background: #e2e8f0;
}

.shot-doc__qr {
    position: absolute;
    right: 0.85rem;
    bottom: 0.85rem;
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.3rem;
    background:
        linear-gradient(#0f172a 50%, transparent 50%),
        linear-gradient(90deg, #0f172a 50%, transparent 50%);
    background-size: 0.35rem 0.35rem;
    opacity: 0.85;
}

.shot-roles {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.shot-role {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.55rem 0.65rem;
    border-radius: 0.55rem;
    background: rgb(255 255 255 / 0.03);
    border: 1px solid rgb(255 255 255 / 0.06);
    font-size: 0.72rem;
}

.shot-role strong { color: #f1f5f9; font-weight: 600; }
.shot-role span {
    color: #64748b;
    font-family: ui-monospace, monospace;
    font-size: 0.65rem;
}

.shot-ai {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding-top: 0.4rem;
}

.shot-ai__prompt,
.shot-ai__reply {
    border-radius: 0.75rem;
    padding: 0.7rem 0.85rem;
    font-size: 0.75rem;
    line-height: 1.45;
}

.shot-ai__prompt {
    align-self: flex-end;
    max-width: 85%;
    background: rgb(37 99 235 / 0.35);
    color: #dbeafe;
    border: 1px solid rgb(59 130 246 / 0.35);
}

.shot-ai__reply {
    align-self: flex-start;
    max-width: 90%;
    background: rgb(255 255 255 / 0.04);
    color: #e2e8f0;
    border: 1px solid rgb(255 255 255 / 0.08);
}

.shot-phone {
    margin: 0 auto;
    width: 11.5rem;
    border-radius: 1.35rem;
    border: 1px solid rgb(255 255 255 / 0.14);
    background: #020617;
    padding: 0.55rem;
    box-shadow: 0 18px 40px rgb(0 0 0 / 0.35);
}

.shot-phone__notch {
    width: 3.2rem;
    height: 0.35rem;
    margin: 0.2rem auto 0.55rem;
    border-radius: 9999px;
    background: rgb(148 163 184 / 0.35);
}

.shot-phone__body {
    min-height: 10.5rem;
    border-radius: 0.9rem;
    background: linear-gradient(180deg, #122033, #0b1524);
    border: 1px solid rgb(255 255 255 / 0.06);
    padding: 0.65rem;
}

.shot-fit {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.shot-fit__truck {
    position: relative;
    height: 4.6rem;
    border-radius: 0.65rem;
    border: 1px solid rgb(255 255 255 / 0.08);
    background:
        linear-gradient(90deg, rgb(37 99 235 / 0.15), transparent 30%),
        linear-gradient(180deg, #122033, #0b1524);
    overflow: hidden;
}

.shot-fit__box {
    position: absolute;
    bottom: 0.55rem;
    border-radius: 0.25rem;
    background: rgb(147 197 253 / 0.75);
    border: 1px solid rgb(191 219 254 / 0.5);
}

.shot-fit__box--a { left: 12%; width: 22%; height: 42%; }
.shot-fit__box--b { left: 38%; width: 18%; height: 58%; background: rgb(96 165 250 / 0.85); }
.shot-fit__box--c { left: 60%; width: 26%; height: 36%; }

@media (prefers-reduced-motion: reduce) {
    .showcase-shot__frame,
    .showcase-shot:hover .showcase-shot__frame,
    .showcase-shot--left:hover .showcase-shot__frame {
        transition: none;
        transform: none;
    }
}

@media (max-width: 640px) {
    .showcase-shot--right .showcase-shot__frame,
    .showcase-shot--left .showcase-shot__frame,
    .showcase-shot:hover .showcase-shot__frame,
    .showcase-shot--left:hover .showcase-shot__frame {
        transform: none;
    }
}
</style>
