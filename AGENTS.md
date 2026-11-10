<!--
  Правила проекта для всех ИИ-агентов (модульный монолит на Symfony).
  Claude Code подключает этот файл через импорт @AGENTS.md в CLAUDE.md.
  Положите в корень репозитория и заполните всё, что помечено TODO.
-->

# Проект

TODO: 2–3 предложения о продукте: что делает приложение, для кого, ключевые сценарии.

## Стек

<!-- TODO: поправьте версии и фронтенд под свой проект -->
- PHP 8.4, Symfony 7.4 LTS, PostgreSQL
- Doctrine ORM + Doctrine Migrations, Symfony Messenger
- Фронтенд: Twig + Symfony UX
- Качество: PHPStan (level max), Deptrac, PHP-CS-Fixer, PHPUnit

## Архитектура

Модульный монолит: каждый модуль — отдельный bounded context в `src/<Module>/`.

<!-- TODO: замените примеры своими модулями -->
- `Billing` — TODO: зона ответственности
- `Catalog` — TODO: зона ответственности
- `Shared` — общий код без бизнес-логики: шины, базовые исключения, общие VO
- `Admin` — только дашборд EasyAdmin

Структура модуля:

```text
src/<Module>/
├── Domain/          # сущности, VO, интерфейсы репозиториев, доменные события и исключения
├── Application/     # use cases: <UseCase>/<UseCase>Command|Query.php + <UseCase>Handler.php
├── Infrastructure/  # Doctrine-репозитории, клиенты внешних API, адаптеры
├── UI/              # Http/ — контроллеры и Request DTO, Cli/ — команды, Admin/ — CRUD EasyAdmin
└── Contract/        # публичный API модуля: интерфейсы, DTO, интеграционные события
```

### Зависимости слоёв (проверяет `deptrac.yaml`)

| Слой | Может зависеть от |
|---|---|
| Domain | только Domain; из библиотек — Doctrine Mapping и Collections, `Doctrine\DBAL\Types\Types`, Symfony Uid |
| Application | Domain, Contract и те же библиотеки, что Domain. Никакого Symfony и Doctrine |
| Infrastructure | Application, Domain, Contract, любые библиотеки |
| UI | Application, Domain, Contract, Symfony. Без Doctrine |
| Contract | ничего, кроме встроенных типов PHP и других классов из Contract |

### Границы модулей (проверяет `deptrac.modules.yaml`)

- Модуль использует только свой код, `Shared` и `Contract/` других модулей.
- Никогда не импортируй `Domain`, `Application`, `Infrastructure` или `UI` чужого модуля.
- Нужны данные другого модуля → интерфейс в его `Contract/`, реализация в его `Application/`.
- Нужно реагировать на событие другого модуля → интеграционное событие в его `Contract/Event/`.
- Единственное исключение — `src/Admin/`: дашборду можно ссылаться на все модули.

## Новая фича = один use case

<!-- TODO: когда сделаете первую фичу вручную, укажите путь к ней ниже -->
Эталон: `src/TODO/Application/TODO/` — копируй его структуру, именование и стиль.

1. Сначала тесты: юнит-тесты домена и обработчика, затем функциональный тест эндпоинта. Тест должен падать до реализации.
2. `Application/<UseCase>/<UseCase>Command.php` (для чтения — `Query.php`): `final readonly`, только скаляры и VO.
3. `Application/<UseCase>/<UseCase>Handler.php`: `final`, один метод `__invoke`, реализует `CommandHandler` или `QueryHandler`. Обработчик только оркестрирует, бизнес-правила живут в сущностях и VO.
4. `UI/Http/<UseCase>Controller.php`: один экшен `__invoke`, `#[Route]`, вход через Request DTO с `#[MapRequestPayload]` и Symfony-валидацией, отправка команды в `CommandBus`.
5. Прогони проверки (см. ниже).

## Шины (Symfony Messenger)

- Интерфейсы `CommandBus`, `QueryBus`, `EventBus` и маркеры `CommandHandler`, `QueryHandler`, `EventHandler` лежат в `src/Shared/Application/Bus/`, реализации на Messenger — в `src/Shared/Infrastructure/Bus/`.
- Обработчики регистрируются через `_instanceof` в `config/services.yaml` по маркерному интерфейсу. В Application не используй `#[AsMessageHandler]` и любые другие классы Symfony.
- `command.bus` обёрнут в middleware `doctrine_transaction`: он сам делает `flush()` и коммит. Метод `save()` репозитория только вызывает `persist()`.
- Команда ничего не возвращает. Query возвращает read-модель (DTO), никогда не сущность.
- Агрегат записывает доменные события; обработчик после `save()` публикует их через `EventBus`. Адаптер `EventBus` ставит `DispatchAfterCurrentBusStamp`, поэтому события обрабатываются только после успешного коммита.

## Соглашения по коду

- `declare(strict_types=1);` в каждом файле.
- Классы `final` по умолчанию; команды, запросы, DTO, события и VO — `final readonly`.
- VO вместо примитивов для значимых понятий: `Email`, `Money`, `InvoiceId`. Идентификаторы — UUID v7 (`Symfony\Component\Uid`).
- Текущее время берётся из `Psr\Clock\ClockInterface` в Application и передаётся в домен аргументом. Никаких `new \DateTimeImmutable()` без аргумента в Domain.
- Доменные исключения наследуют базовые из `src/Shared/Domain/Exception/` (`NotFound`, `Conflict`, `InvalidInput`); в HTTP-коды их переводит один подписчик в `src/Shared/UI/Http/`.
- Именование: `CreateInvoiceCommand` / `CreateInvoiceHandler`, `GetInvoiceQuery`, `InvoiceRepository` (интерфейс в Domain) / `DoctrineInvoiceRepository` (Infrastructure), события в прошедшем времени — `InvoiceCreated`.
- Маппинг Doctrine — атрибутами на сущностях. `EntityManager` и `QueryBuilder` — только в Infrastructure.
- Миграции: `bin/console doctrine:migrations:diff`, затем проверь сгенерированный SQL. Применённые миграции не редактируй.
- Справочники и админка без бизнес-логики — через EasyAdmin, без use case: CRUD-контроллеры в `src/<Module>/UI/Admin/`, дашборд в `src/Admin/`.

## Тесты

- `tests/` повторяет структуру `src/`: `tests/<Module>/{Domain,Application,UI}/`.
- Domain — чистые юнит-тесты без контейнера и БД.
- Application — обработчики с in-memory репозиториями из `tests/<Module>/Fake/`, а не с моками репозиториев.
- UI — функциональные тесты на `WebTestCase`: статус-коды, ошибки валидации, изменения в БД.

## Проверки

После каждого изменения прогоняй всё и чини, пока не станет зелёным. Задача не считается выполненной, пока хоть одна проверка красная.

```bash
composer check                                                  # всё сразу
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
vendor/bin/deptrac analyse                                      # слои
vendor/bin/deptrac analyse --config-file=deptrac.modules.yaml   # модули
vendor/bin/phpunit
```

Разобраться в конфигурации помогают `bin/console debug:router`, `debug:container` и `debug:messenger`.

## Без моего явного согласия нельзя

- Глушить ошибки: `@phpstan-ignore`, пополнение baseline, `skip_violations` в Deptrac.
- Менять правила в `deptrac*.yaml`. Исключение: добавить новый модуль по образцу существующих.
- Удалять, пропускать или ослаблять тесты, чтобы они прошли.
- Добавлять composer-зависимости.
- Редактировать применённые миграции.
- Додумывать бизнес-правила. Если требование неясно — спроси.

## Как работаем

- Задача больше одного use case → сначала план (файлы, классы, тесты), затем жди подтверждения.
- Один use case за раз. После него — проверки и короткий отчёт: что сделано, что осталось.
- Перед изменениями в незнакомой части кода прочитай соседние файлы и эталон.
- Не создавай абстракций «на будущее» и не рефактори то, о чём не просили.
- Новый модуль: структура как выше + слой в `deptrac.modules.yaml` по образцу `Billing` + добавить его в список разрешённых для `Admin`.
