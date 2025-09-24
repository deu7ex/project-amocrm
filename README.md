# 🚀 AmoCRM Integration (Laravel + Google Sheets + VK)

Интеграция для синхронизации данных между **Google Sheets**, **AmoCRM** и **ВКонтакте**.  
Позволяет автоматизировать процесс создания и обновления сделок, а также связать переписку в VK с amoCRM.

---

## 📌 Возможности

- 📊 Синхронизация сделок из **Google Sheets** → **AmoCRM**
- 👤 Автоматическое создание/поиск **контактов** и **компаний** в amoCRM
- 🔄 Обновление ID сделки обратно в Google Sheets
- 💬 Интеграция с **ВКонтакте** через Callback API:
    - новые сообщения из группы создают сделки и примечания в amoCRM
    - исходящие сообщения из amoCRM отправляются в VK

---

## ⚙️ Установка

### 1. Зависимости
- [Docker][docker-install]
- [ngrok][ngrok]
- [Clasp][clasp]

### 2. Клонирование проекта
```bash
git clone git@github.com:deu7ex/okk-service.git
cd okk-service
```

### 3. Настройка окружения
Скопировать `.env.example` в `.env` (в корне проекта и папке `docker`):

```bash
cp .env.example .env
```

В `.env` указываются:

```dotenv
# VK
VK_AMOCRM_FIELD_ID = <ID поля в amoCRM>
VK_CONFIRM_CODE    = <строка подтверждения для Callback API>
VK_GROUP_ID        = <ID группы>
VK_API_VERSION     = 5.199
VK_API_TOKEN       = <токен сообщества ВК>

# amoCRM
AMOCRM_DOMAIN         = example.amocrm.ru
AMOCRM_CLIENT_ID      = <из интеграции>
AMOCRM_CLIENT_SECRET  = <из интеграции>
AMOCRM_REDIRECT_URI   = https://xxxxxx.ngrok-free.app/api/amocrm/callback

AMOCRM_PIPELINE_ID = <id воронки>
AMOCRM_STATUS_ID   = <id статуса>

GOOGLE_APPLICATION_CREDENTIALS = "<путь до файла credentials.json>"

# Google Sheets
AMOCRM_SPREADSHEET_ID      = <id таблицы>
AMOCRM_AMOCRM_ID_COLUMN    = <буква колонки, куда сохраняется ID сделки>
AMOCRM_SHEET_COLUMN_INDEX  = <номер колонки с ID сделки>
AMOCRM_SHEET_NAME_INDEX    = <номер колонки с названием сделки>
AMOCRM_SHEET_PRICE_INDEX   = <номер колонки с ценой>
AMOCRM_SHEET_CONTACT_INDEX = <номер колонки с ФИО контакта>
AMOCRM_SHEET_PHONE_INDEX   = <номер колонки с телефоном>
AMOCRM_SHEET_EMAIL_INDEX   = <номер колонки с email>
```

### 4. Запуск Docker
```bash
./docker-run.sh
```

Остановить:
```bash
./docker-run.sh down
```

Миграции и сиды:
```bash
docker exec okk-app php artisan migrate --seed
```

---

## 🌐 Ngrok (публичный доступ)

Для интеграции с amoCRM нужен публичный URL.

Запуск туннеля:
```bash
ngrok http 85
```

Ngrok выдаст адрес:
```
https://xxxxxx.ngrok-free.app -> http://localhost:85
```

Указать в amoCRM:
- Callback URL:
  ```
  https://xxxxxx.ngrok-free.app/api/amocrm/callback
  ```
- Webhook на изменение сделки/контакта:
  ```
  https://xxxxxx.ngrok-free.app/api/amocrm/sheet/google
  ```

---

## 📥 Настройка вебхуков ВК

1. Зайти в **Управление сообществом → Работа с API → Callback API**
2. Указать адрес:
   ```
   https://xxxxxx.ngrok-free.app/api/vk
   ```
3. На событие `confirmation` сервер вернёт `VK_CONFIRM`.

---

## ✍️ Работа с Google Apps Script через Clasp

Чтобы не редактировать код скриптов прямо в UI Google, можно использовать [Clasp][clasp].


> ✅ Таким образом, скрипты можно хранить в Git и версионировать вместе с Laravel-проектом.

---

## 👨‍💻 Технологии

- PHP 8.2
- Laravel 10+
- RabbitMQ
- Docker
- Ngrok
- Clasp

---

[docker-install]: https://docs.docker.com/install/#supported-platforms
[ngrok]: https://ngrok.com/
[clasp]: https://github.com/deu7ex/amo-sync

