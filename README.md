# alexw.antibot — антибот-защита форм для 1С-Битрикс (БУС)

Поведенческий скоринг + паспорт устройства + HMAC-токены. Без внешних сервисов и капчи на первом уровне.

## Что умеет

- **Бот-флаги (8):** webdriver, пропатченный webdriver, domAutomation, PhantomJS, протёкший Node.js, headless UA, пустые languages/plugins, SwiftShader (headless GPU).
- **Поведенческий скоринг:** время заполнения формы, движения/дистанция мыши, клики, клавиши, honeypot-поле.
- **Паспорт устройства:** canvas-фингерпринт (SHA-256 от растеризации текста несуществующим шрифтом + дуг, стабилен в инкогнито) + сбор железа (GPU через WebGL, экран×DPR, платформа, ядра, память, таймзона).
- **Привязка токена к устройству:** fp зашивается в токен при выдаче; payload с чужого устройства получает `fpMismatch` (+40).
- **Трекинг устройств на каждом хите:** fp считается за ~13 мс (canvas 12,9 мс + SHA-256 0,5 мс), отправляется раз в сессию; таблица `alexw_antibot_device` ведёт fp → user_id, хиты, железо; картинка canvas сохраняется в `/upload/alexw.antibot/fp/<hash>.png` (опция `save_fp_image`, дедуп по хешу).
- **Решения:** pass / captcha / block по порогам в настройках; журнал всех проверок с устройством в админке.

## API

- `POST /bitrix/services/main/ajax.php?action=alexw:antibot.api.issue` (поле `fp`) → `{token, iat}`
- `POST ...?action=alexw:antibot.api.check` (поля `token`, `payload`) → `{ok, decision, score, reasons}`
- `POST ...?action=alexw:antibot.api.track` (поля `fp`, `device`, опционально `image`) → `{ok, needImage?}`
- Интеграция в свою форму: `\Alexw\Antibot\Guard::check($token, $payloadJson)` на сервере; JS — `window.AlexwAntibot.protect(form)` или атрибут `data-antibot` на форме.

## Структура

- `lib/` — Settings (COption!), TokenService (HMAC), Score (веса), Guard, LogTable, Controller/Api, обработчики.
- `install/js/agent.js` — исходник JS-агента.
- `install/js/agent.obf.js` — обфусцированная сборка (отдаётся на сайты; если её нет, отдаётся исходник).

## Сборка обфусцированного агента

```
npx javascript-obfuscator install/js/agent.js --output install/js/agent.obf.js \
  --options-preset high-obfuscation --debug-protection false --self-defending true
```

## Грабли, учтённые в коде

- `Bitrix\Main\Option` (D7) фаталит на раннем bootstrap (OnPageStart) и отсутствует в ядре 25.100 — везде `COption` / `Bitrix\Main\Config\Option`.
- `DeleteDirFilesEx` — procedural-функция, не метод CFile.
- Формат действия Engine для вендор-модуля: `alexw:antibot.api.check` (двоеточие); в `.settings.php` нужен `controllers.defaultNamespace`.
- nginx с `expires max` для js — PageHandler версионирует агент по mtime.
