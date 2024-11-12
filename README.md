# URL Shortener

URL Shortener е мощно приложение за съкращаване на линкове, създадено да помага на потребителите да съкращават и управляват
своите линкове по-лесно и ефективно. С поддръжка на акаунти, дашборд, пароли и допълнителни опции, това приложение е перфектното
решение за всички, които искат да опростят управлението на линкове.

## Функционалности

### 🧑‍💻 Потребителски акаунти
- Потребителите могат да създават собствени акаунти, за да съхраняват своите линкове.
- Логин система с потребителско име и парола.
- Възможност за промяна на данните на потребителя.

### 📊 Dashboard
- Дашбордът предоставя лесен достъп до всички създадени линкове на потребителя.
- Информация за статистика, като брой посещения за всеки линк.
- Възможност за бързо създаване, редакция и изтриване на линкове директно от дашборда.

### 🔐 Защита с парола
- Потребителите могат да добавят парола към съкратените линкове, за да ограничат достъпа до тях.
- Паролите защитават линковете, като предоставят сигурност и контрол върху достъпа.

### 📉 Статистика и анализи
- Преглед на статистики на линковете.

### 🖇 Лесно управление на линкове
- Създавайте кратки и лесни за запомняне URL-и само с няколко клика.
- Редактирайте линковете и задавайте описания за по-добра организация.
- Лесен интерфейс за копиране на краткия URL.

### 📊 Създаване на база данни

- Име на база данни - codewars
    - таблица users utf8mb4_general_ci
        - id - (auto-increment!) (int 11)
        - username - (varchar(16))
        - email - (varchar(255))
        - password - (varchar(255))
        - created links - (longtext)
        - created_at - (datetime)
        - updated_at - (datetime)
        - last_login - (datetime)
    - таблица shortener utf8mb4_general_ci
        - id (auto-increment) (int 36)
        - original_url (varchar(2083))
        - shortened_url (varchar(10))
        - created_at (datetime)
        - expiration_date (datetime)
        - click_count (int(11))
        - max_clicks (int(11))
        - password (varchar(255))

## Изисквания

- PHP 8.2
- MySQL база данни (XAMPP)

---
CodeWars 2024 - Victor Hristov Drumev
