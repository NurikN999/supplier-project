# Supplier Project

Админ-панель для управления поставщиками с изоляцией данных между организациями.

Каждая организация (тенант) ведёт свой список поставщиков, тегов, товаров и заказов.
Данные одной организации недоступны другой.

**Стек:** Laravel 13, Filament 4, PHP 8.5, PostgreSQL 15, Docker.

## Запуск

Нужен только Docker.

```bash
git clone git@github.com:NurikN999/supplier-project.git
cd supplier-project

cp .env.example .env
docker compose up -d --build

docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
```

Панель: **http://localhost:8026/admin**

Все `artisan`-команды выполняются внутри контейнера `php` — с хоста имя `postgres`
не резолвится, и подключение к базе не сработает.

## Тестовые пользователи

Сидер создаёт две организации со своими данными — на них видно, что изоляция работает.
Войдите под одним пользователем, потом под другим: списки не пересекаются.

| Организация  | Логин               | Пароль     |
|--------------|---------------------|------------|
| Acme Trading | `acme@example.com`  | `password` |
| Beta Foods   | `beta@example.com`  | `password` |

## Тесты

```bash
docker compose exec php php artisan test
```

Тесты идут на sqlite в памяти (см. `phpunit.xml`), базу в Docker они не трогают —
данные в `supplier_db` останутся на месте.

## Что внутри

### Мультитенантность

Организация определяется по слагу в URL: `/admin/acme`, `/admin/beta`.
Скоупинг делает встроенная мультитенантность Filament — глобальный скоуп на
моделях и автоподстановка `organization_id` при создании.

Глобальный скоуп покрывает таблицы ресурсов, но **не** покрывает опции связей
(селекты, фильтры, attach-экшены) — там скоуп проставлен явно через
`whereBelongsTo(Filament::getTenant())`. Сверху лежит `TenantPolicy` — одна
политика на все модели тенанта, как второй замок на той же двери.

### Прайс-листы

Один товар могут поставлять разные поставщики, каждый по своей цене — цены лежат
в пивоте `supplier_products`. Управляются с обеих сторон: «Прайс-лист» в карточке
поставщика и «Предложения» в карточке товара.

### Жизненный цикл заказа

```
draft ──► placed ──► received
  │         │
  └────► cancelled ◄┘
```

Переходы описаны в `App\Enums\PurchaseOrderStatus` и проверяются в модели
`PurchaseOrder`. Неполученный заказ нельзя «принять», полученный нельзя отменить —
кнопка не появится, а модель всё равно бросит исключение.

**Приёмка меняет состояние системы:** в одной транзакции пишется движение по
складу (`stock_movements`) на каждую строку заказа и увеличивается остаток
(`stocks.qty_on_hand`).

Цена строки берётся из прайс-листа именно этого поставщика и сохраняется в заказе
как исторический факт — если поставщик потом поменяет цену, старый заказ не поедет.

## Схема базы

Схема в третьей нормальной форме. Вычисляемые значения не хранятся: сумма заказа
считается из строк, `stocks.qty_on_hand` — материализованный остаток, источник
правды — `stock_movements`.

```
organizations ─┬─ users
               ├─ suppliers ─┬─ supplier_tag ── tags
               │             └─ supplier_products ── products
               ├─ products ── stocks
               └─ purchase_orders ─┬─ purchase_order_items ── products
                                   └─ stock_movements
```

Уникальность — составная, вместе с `organization_id`: у разных организаций могут
быть одинаковые SKU, названия тегов и номера заказов.

## Полезные команды

```bash
docker compose exec php php artisan migrate:fresh --seed   # пересобрать базу
docker compose exec php vendor/bin/pint                    # форматирование
docker compose exec php php artisan tinker                 # консоль
docker compose logs -f php                                 # логи
```
