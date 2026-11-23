# alexw.antibot — антибот-защита форм для 1С-Битрикс (БУС)

Многослойная защита форм от ботов без капчи на первом уровне: поведенческий скоринг, паспорт устройства (canvas-фингерпринт), HMAC-токены с привязкой к устройству, журнал и аналитика устройств в админке. Всё своё, без внешних сервисов — данные не уходят за пределы сайта.

Вдохновлено реверсом антифрода Т-Банка (canvas-фингерпринт `nf`, бот-детекты, поведенческий анализ).

## Возможности

**Детект автоматизации (8 флагов)**
`webdriver`, пропатченный `webdriver` (стелс-плагины), `domAutomation`, PhantomJS, протёкший Node.js, headless User-Agent, пустые languages/plugins, SwiftShader (GPU headless-режима).

**Поведенческий скоринг**
- время заполнения формы (слишком быстро — бот);
- активность мыши/клавиатуры и её отсутствие;
- **дисперсия интервалов между клавишами** — у робота ритм аномально ровный;
- **направленность траектории мыши по окнам** — робот тянется к цели по идеальной прямой, у человека рука дрожит;
- honeypot-поле.

**Паспорт устройства**
- canvas-фингерпринт (SHA-256 от растеризации текста несуществующим шрифтом + дуг) — стабилен между сессиями и **работает в инкогнито**;
- железо: GPU (WebGL renderer), экран×DPR, платформа, ядра, память, таймзона;
- fp зашивается в токен: payload с другого устройства получает `fpMismatch`;
- генерация fp ~13 мс — считается на любом хите без влияния на скорость.

**Трекинг и аналитика**
- привязка устройства к аккаунту БУС (fp → user_id) на каждом хите;
- таблица устройств: хиты, first/last seen, железо, PNG картинки canvas-фингерпринта;
- мультиаккаунты и шеринг аккаунтов видны одним запросом.

**Админка**
Настройки порогов и IP-списков, журнал проверок (скор, причины, устройство), реестр устройств с превью картинки.

## Установка

1. Скопировать модуль в `local/modules/alexw.antibot`.
2. Установить через админку (Marketplace → Установленные решения) или CLI:

```php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/modules/alexw.antibot/install/index.php';
(new alexw_antibot())->DoInstall();
```

Установка создаёт таблицы `alexw_antibot_log` и `alexw_antibot_device`, копирует admin-страницы и JS, регистрирует события и секретный ключ.

## Быстрый старт

**1. JS-агент на страницах.** Модуль сам подключает `agent.obf.js` на всех публичных страницах (событие `OnPageStart`). Вручную:

```php
$APPLICATION->AddHeadScript('/bitrix/js/alexw.antibot/agent.obf.js');
```

**2. Защита формы одной строчкой** — добавьте форме атрибут:

```html
<form data-antibot method="post" action="/ajax/form.php">...</form>
```

Агент сам вставит honeypot и при сабмите добавит скрытые поля `ab_token` и `ab_payload`.

**3. Проверка на сервере:**

```php
\Bitrix\Main\Loader::includeModule('alexw.antibot');

$result = \Alexw\Antibot\Guard::check($_POST['ab_token'] ?? '', $_POST['ab_payload'] ?? '');
// $result = ['ok' => bool, 'decision' => 'pass|captcha|block', 'score' => int, 'reasons' => string[]]

if ($result['decision'] === 'block') {
    // отклонить
} elseif ($result['decision'] === 'captcha') {
    // показать капчу / дополнительную проверку
}
```

**4. Ручной режим (AJAX-форма):**

```js
const token = await AlexwAntibot.issueToken();
const payload = await AlexwAntibot.buildPayload(honeypotValue);
// отправить token + payload на сервер
```

## HTTP API

| Endpoint | Поля | Ответ |
|---|---|---|
| `.../ajax.php?action=alexw:antibot.api.issue` | `fp` | `{token, iat}` |
| `...?action=alexw:antibot.api.check` | `token`, `payload` | `{ok, decision, score, reasons}` |
| `...?action=alexw:antibot.api.track` | `fp`, `device`, `image?` | `{ok, needImage?}` |

## Скоринг

| Сигнал | Вес |
|---|---|
| honeypot заполнен | 100 |
| webdriver / domAutomation | 60 |
| PhantomJS / SwiftShader | 50 |
| headless UA / пропатченный webdriver / fpMismatch | 40 |
| Node.js / слишком быстрое заполнение / robotic_typing | 30 |
| no_activity | 25 |
| straight_mouse (≥80% окон прямые) | 20 |
| noLanguagesPlugins / robotic_mouse | 15 |

Пороги по умолчанию: `captcha` от 50, `block` от 80 — настраиваются в админке.

## Настройки (Сервисы → Антибот → Настройки)

| Опция | По умолчанию | Назначение |
|---|---|---|
| `enabled` | Y | Вкл/выкл защиту |
| `min_fill_ms` | 1500 | Мин. время заполнения формы |
| `captcha_score` | 50 | Скор для капчи |
| `block_score` | 80 | Скор для блокировки |
| `token_ttl` | 3600 | TTL токена, сек |
| `ip_whitelist` / `ip_blacklist` | — | Списки IP (`*` — префикс) |
| `save_fp_image` | N | Сохранять PNG фингерпринта в `/upload/alexw.antibot/fp/` |

## Проверено атаками

Модуль тестировался живыми атаками Playwright на себя:

| Сценарий | Результат |
|---|---|
| Живой человек | pass, скор 0 |
| Наивный Playwright headless | block 100 (webdriver, headlessUA, SwiftShader) |
| Стелс headless (анти-webdriver + имитация человека) | block 90 (headlessUA, SwiftShader) |
| Стелс headed (реальный браузер + имитация) | captcha 50 (robotic_typing, straight_mouse) |

## Сборка обфусцированного агента

```
npx javascript-obfuscator install/js/agent.js --output install/js/agent.obf.js \
  --options-preset high-obfuscation --debug-protection false --self-defending true
```

## Ограничения и честные оговорки

- Защита — «высокий забор», не стена: обход требует реального браузера и качественной имитации человеческой моторики (антидетект-фреймворки). Против массового спама и типовых ботов — достаточно.
- Fingerprint устройства — технический идентификатор, попадает под требования о защите данных: добавьте пункт в политику конфиденциальности сайта, сохранение картинок/железа отключается настройкой.
- Общие устройства (офисный ПК) имеют один fp на несколько людей; fp — сигнал, а не приговор.
- Совместимость: ядро БУС 25.100+, PHP 8.0+, MySQL. В коде учтено: ранний bootstrap — только `COption`, `Bitrix\Main\Config\Option` вместо удалённого `Bitrix\Main\Option`.

## Структура

```
install/          установщик, admin-страницы, JS (исходник + obf-сборка)
lib/
  Settings.php      опции (COption)
  TokenService.php  HMAC-токены с привязкой к устройству
  Score.php         веса и правила скоринга
  Guard.php         полная проверка отправки формы
  DeviceTracker.php трекинг устройств и привязка к аккаунтам
  LogTable.php      журнал проверок (ORM)
  DeviceTable.php   реестр устройств (ORM)
  Controller/Api.php  HTTP API
  Handler/          OnPageStart (подключение JS), админ-меню
```

## Лицензия

MIT
