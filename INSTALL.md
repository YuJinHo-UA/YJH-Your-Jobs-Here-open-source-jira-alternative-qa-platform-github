# 🚀 Установка и запуск YJH — Your Job's Here

## Системные требования
- PHP 8.0 или выше
- Расширения: pdo_sqlite, json, mbstring
- Веб-сервер: Apache / Nginx (или встроенный сервер PHP)

---

## ⚡ Быстрый старт (встроенный сервер)

```bash
cd C:\xampp\htdocs\YJH
php -S localhost:8000
```

Открой браузер: http://localhost:8000

---

## 🐘 Запуск через XAMPP

1. Скопируй проект в C:\xampp\htdocs\YJH
2. Запусти XAMPP Control Panel
3. Нажми Start напротив Apache
4. Открой http://localhost/YJH

---

## 🚀 Запуск через Laragon

1. Скопируй проект в C:\laragon\www\YJH
2. Запусти Laragon → Start All
3. Открой http://YJH.test

---

## 🔐 Безопасная настройка (обязательно для публичного доступа)

Задай переменные окружения перед запуском:

- `YJH_ENCRYPTION_KEY` — длинный уникальный секрет для шифрования
- `OPENAI_API_KEY` — ключ OpenAI (если используешь AI-модуль)
- `YJH_AI_PROVIDER=openai` — чтобы AI работал в реальном режиме

Если `YJH_ENCRYPTION_KEY` не задан, приложение создаст локальный ключ в `.yjh-secrets/encryption.key`.
Этот файл приватный и не должен попадать в git.

## 🔑 Вход в систему

Логин: admin@yujin.ho  
Пароль: admin123

---

## 🗄️ База данных

Используется SQLite. Файл database.sqlite создаётся автоматически при первом запуске.

---

## 🐛 Возможные ошибки

### PHP не найден
```bash
C:\xampp\php\php.exe -S localhost:8000
```

### Порт занят
```bash
php -S localhost:8080
```

### Отказано в доступе
Запусти командную строку от имени администратора.

---

## 📁 Структура проекта

```
YJH/
├── index.php
├── config/
├── includes/
├── api/
├── assets/
├── lang/
├── uploads/
└── database.sqlite
```

---

## 🧪 Проверка

1. Открой сайт
2. Войди под admin
3. Создай баг, тест, wiki-страницу
4. Переключи тему и язык

Если всё работает — проект готов! 🔥
