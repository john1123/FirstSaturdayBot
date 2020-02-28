# FirstSaturdayBot
Бот для Телеграм, который помогает вести учёт изменений для игроков на Ingress FirstSaturday.
Бот позволяет проводить неограниченное число событий на одном сервере (например ФС в разных городах) и одновременно.
Человек может быть участником только одного события одновременно.

Установка бота
------------
0. В Telegram у BotFather создаёте своего бота
1. Создать папку data и дать доступ на запись туда
2. В файле bot.php в строке 5 прописать свой токен, который получен у BotFather
3. Добавить для крона задачу вида:
```
/opt/php/5.4/bin/php -f /var/www/ПутьКСкрипту/bot.php >/dev/null 2>&1
```
   Частота выполнения задачи - у автора 1 минута. Минимальная версия для крона PHP: 5.4

После регистрации бота, нужно сообщить о нём серверу телеграм, установив для данного токена хук с адресом вашего бота.
Сделав это можно, один раз обратившись по адресу:
```
https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<адрес и путь к скрипту>/bot.php
```

Удалить установленный хук можно обратившись по адресу:
```
https://api.telegram.org/bot<TOKEN>/deleteWebhook
```

События
------------
1. Команда "Создать событие" имеет такой вид:
```
Событие создать "KyivFS - Тест" 28.01.2020 10:00 21:00 vasya petya
```
и состоит из следующих частей:
- Событие создать (собственно команда)
- "Название события". С кавычками. в примере KyivFS - Тест. Если в названии нет пробелов, можно без кавычек.
- Дата дачала ДД.ММ.ГГГГ (28 января)
- Время начала ЧЧ:ММ:СС (10:00)
- Время конца (21:00)
- Ники администраторов (оргов) через пробел (@vasya @petya)

Команда создаёт событие и возвращает ссылку на него вида https://t.me/<bot>?start=id0
Где id0 - идентификатор нового события.
Ссылка предназначена для распространения среди участников.
Перешедшие по этой ссылке и кликнувшие "старт" откроется чат с ботом и они сразу будут зарегистрированы на новое событие.

2. Команда "Событие удалить" имеет вид:
```
Событие удалить Название события
```
и состоит из частей:
- Удалить событие (собственно команда)
- Название события (без кавычек - хоть с пробелами, хоть без)

Требования
------------
* SSL на хостинге (требуется телеграмм-ом)
* PHP версия 5.4 или старше
* PHP расширение php_xml включено
* PHP расширение php_gd2 включено (если не компилируется)
* CRON для автоматической отправки сообщений

Был использован краткий синтаксис записи массивов [] вместо array().
Если переписать, требуемая версия PHP опустится до 5.2, необходимой для PHPExcel

Примечания
------------
Основа бота - статическй метод IngressProfile::parseProfile(); Метод парсит профиль из ингресс и возвращает в виде ассоциативного массива.
ВНИМАНИЕ. Файл IngressProfile.php всегда должен быть самой последней версии (из ветки dev?), так как Ниантики иногда добавляют новые строки в статистику, а старые версии файла о ней не знают. Разбор профайла с такой строкой будет возвращать ошибку. (в TODO - исправить ситуацию)
Если разбор профиля перестаёт работать - это основное, на что стоит обратить внимание!

 Известные проблемы
 ------------
 - Нельзя создать события больше одного дня. Начало и конец события имеют одинаковое число.
 - Колонки для итоговой таблицы настраиваются для всех событий стразу. Невозможно создать разные таблицы для разных событий. У всех событий в таблицк столбцы одинаковые.


