Пример интеграции php-приложения с сервисом Простые Звонки
==========================================================

Простые Звонки - сервис для интеграции клиентских приложений (Excel, 1C и ERP-cистем) с офисными и облачными АТС. Клиентское приложение может общаться с сервером Простых Звонков через единый API, независимо от типа используемой АТС. 

В данном примере мы рассмотрим процесс подключения к серверу Простых Звонков веб-приложения, написанного на PHP. Мы начнём с веб-приложения, выводящего на экран список клиентов из базы данных, и добавим в него следующие функции:

- отображение всплывающей карточки при входящем звонке;
- звонок клиенту по клику на телефоный номер.

Каждый шаг данного руководства представлен собственным тэгом в репозитории. Например, чтобы посмотреть версию кода, соответствующую первому шагу, нужно выполнить следующую команду:

```bash
git checkout 1
```

> Если вы не знакомы с системой контроля версий git - ничего страшного. Просто загрузите [архив] с файлами, распакуйте его и откройте в любимом редакторе.

**Обратите внимание:** данное приложение написано исключительно в образовательных целях. Для того, чтобы сделать код прощё и понятней, в нём намеренно опущены такие важные штуки, как обработка ошибок и граничных ситуаций, валидация и экранирование данных.

Для начала посмотрим, как устроена интеграция CRM-системы с сервером Простых Звонков:

![Функциональная схема](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/functional-diagram.png)

Модуль PHP SDK запускает процесс listener_process.php, который устанавливает постоянное соединение с сервером CTI по протоколу WebSockets. Модуль обрабатывает получаемые от сервера события (например, событие входящего звонка или запроса на перевод) и сохраняет их в отдельные файлы в папку storage/events. Клиент отправляет HTTP-запросы (например, на создание исходящего звонка или на получение информации о входящих звонках) CRM-системе с помощью технологии AJAX.

Шаг 0. Исходное приложение
--------------------------

Наше исходное приложение умеет показывать список клиентов. В качестве базы данных используется файл storage/contacts.csv. Прочитанные из файла строки отображаются в виде таблицы.

![Исходное приложение](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/tinycrm-origin.png)

Шаг 1. Настройка подключения к серверу
--------------------------------------

Скачаем и распакуем архив [php-sdk]. В папке ProstieZvonki находится бибиотека, ответственная за взаимодействие с сервером Простых Звонков, а в папке TestSuite - утилиты для тестирования. Скопируем обе папки в наш проект. Переименуем файл config.ini.example в config.ini. 

> У веб-сервера должны быть права на запись в папку storage и её подпапки, а также в папку lib/ListenerProcess и файл config.ini. 

Теперь нужно скачать [тестовый сервер и диагностическую утилиту](https://github.com/vedisoft/pz-developer-tools).

Запустим тестовый сервер:

    > TestServer.exe -r

и подключимся к нему диагностической утилитой:

    > Diagnostic.exe

    [events off]> Connect ws://localhost:10150 asd
    Успешно начато установление соединения с АТС

Тестовое окружение настроено.

Добавим на страницу веб-приложения индикатор состояния соединения с сервером и кнопку, по нажатию на которую мы будем устанавливать соединение.

```html
<span id="button" class="btn pull-right">Соединить</span>
<span id="indicator" class="badge">Проверка соединения...</span>
```

Теперь наше приложение выглядит так:

![Индикатор состояния соединения](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/connection-indicator.png)

Приложение будет управлять соединением посредством ajax-запросов, поэтому нам нужно подготовить небольшой обработчик. Создадим в папке public файл ajax.php и добавим в него следующий код:

```php
<?php

require_once '../ProstieZvonki/ProstieZvonki.php';

$pz = ProstieZvonki::getInstance();

echo call_user_func($_GET['action'], $pz, $_GET);
```

Теперь, чтобы добавить новый тип ajax-запроса, нам нужно всего лишь объявить функцию с двумя аргументами: объектом класса ProstieZvonki (он будет выполнять всю работу по взаимодействию с сервером) и массив с GET-параметрами.

> Подробное описание API библиотеки php-sdk можно найти [тут](https://github.com/vedisoft/php-sdk#api).

Добавим функции для подключения, отключения и получения информации о состоянии соединения:

```php
function is_connected(ProstieZvonki $pz, array $input) {
	return $pz->isConnected();
}

function connect(ProstieZvonki $pz, array $input) {
	$pz->connect(array(
		'client_id'     => '101',       // Пароль
		'client_type'   => 'tinyCRM',   // Тип приложения
		'host'          => 'localhost', // Хост сервера
		'port'          => '10150',     // Порт сервера
	));
}

function disconnect(ProstieZvonki $pz, array $input) {
	$pz->disconnect();
}
```

Перейдём к настройки клиентской части приложения.

Добавим обработчик события кнопки. По нажатию кнопки будет выполнять запрос на подключение либо отключение соединения с сервером:

```js
$('#button').on('click', function() {
	if ($(this).text() === 'Соединить') {
		$.get('ajax.php', { 'action': 'connect' });
	} else {
		$.get('ajax.php', { 'action': 'disconnect' });
	}
});
```

Также добавим запрос информации о состоянии соединения с интервалом в одну секунду:

```js
setInterval(function() {
	$.getJSON(
		'ajax.php',
		{ 'action': 'is_connected' },
		function(data) {
			if (data) {
				$('#indicator')
					.removeClass('badge-important')
					.addClass('badge-success')
					.text('Соединение установлено');
				$('#button').text('Разъединить');
			} else {
				$('#indicator')
					.removeClass('badge-success')
					.addClass('badge-important')
					.text('Нет соединения');
				$('#button').text('Соединить');
			}
		}
	);
}, 1000);
```

Попробуем подключиться к серверу:

![Соединение установлено](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/connection-established.png)

Шаг 2. Исходящие звонки кликом по номеру
----------------------------------------

Для начала, сделаем номера телефонов клиентов ссылками:

```html
<td width="1%" nowrap>
    <span title="Позвонить" class="btn-link make-call">
        <?= $contact[1] ?>
    </span>
</td>
```

![Делаем телефоны ссылками](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/phone-links.png)

Добавим функцию в файл ajax.php:

```php
function call(ProstieZvonki $pz, array $input) {
	$pz->call($input['from'], $input['to']);
}
```

Теперь добавим на страницу обработчики нажатия на ссылки:

```js
$('body').on('click', '.make-call', function() {
	var user_phone   = '101'; // Внутренний номер сотрудника.
	                          // В многопользовательском приложении этот номер
	                          // будет храниться в настройках пользователя
	var client_phone = $(this).text().trim();

	$.get('ajax.php', { 'action': 'call', from: user_phone, to: client_phone });
});
```

Кликнув на номер клиента, посмотрим на вывод тестового сервера:

```
Call event from CRM: src = 101, dst = +7 (343) 0112233
```

Как мы видим, сервер получил запрос на создание исходящего звонка с номера 101 на номер +7 (343) 0112233.

Шаг 3. Всплывающая карточка входящего звонка
--------------------------------------------

Добавим очередную функцию в ajax.php:

```php
function get_events(ProstieZvonki $pz, array $input) {
	return json_encode($pz->getEvents());
}
```

Для отображения всплывающих карточек воспользуемся плагином [jQuery Noty](http://needim.github.io/noty/).

Скачаем архив с плагином и распакуем его в папку `js/noty`. Теперь нужно подключить все необходимые файлы:

```js
<script src="js/noty/jquery.noty.js"></script>
<script src="js/noty/layouts/bottomRight.js"></script>
<script src="js/noty/themes/default.js"></script>
```

Подготовим функцию для поиска контактов по номеру телефона:

```js
function sanitizePhone(phone)
{
    return phone.replace(/\D/g, '').slice(-10);
}

function findByPhone(contacts, phone) {
    return contacts.filter(function (contact) {
        return sanitizePhone(contact.phone) === sanitizePhone(phone);
    }).shift();
}
```

> Как видите, мы воспользовались вспомогательной функцией для очистки номера телефона от посторонних символов и кода страны. Таким образом, поиск по номерам `+7 (343) 0112233` и `83430112233` будет выдавать одинаковый результат, что там и нужно.

Теперь у нас есть вся необходимая информация, и мы можем заняться непосредственно отображением карточек. Подготовим две вариации шаблона карточки: для случая когда номер найден в базе, и когда он не найден:

```js
function getNotyText(phone, name) {
    return '<span class="pz_noty_title">Входящий звонок</span>' +
        (name ? '<span class="pz_noty_contact">' + name + '</span>' : '') +
        '<span class="pz_noty_phone btn-link make-call">' + phone + '</span>' +
        '<span class="pz_noty_copyright">' +
            '<img src="img/pz.ico">' +
            '<a target="_blank" href="http://prostiezvonki.ru">Простые звонки</a>' +
        '</span>';
}
```

Напишем функцию, при вызове которой в правом нижнем углу экрана будет появляться карточка. Чтобы не загромождать экран, при очередном звонке будем скрывать старую карточку.

```js
function showCard(phone) {
    var contact = findByPhone(storage, phone);
    var text = contact
            ? getNotyText(contact.phone, contact.name)
            : getNotyText(phone);

    $.noty.closeAll();
    noty({
        layout: 'bottomRight',
        closeWith: ['button'],
        text: text
    });
}
```

Осталось добавить функцию, которая будет раз в секунду запрашивать список полученных событий. Если среди событий находится событие входящего вызова на менеджера (то есть событие с типом "2"), будет отображена карточка с информацией по этому вызову.

> Подробное описание всех типов событий представлено в [документации](https://github.com/vedisoft/php-sdk#--4).

```js
setInterval(function() {
    $.getJSON(
        'ajax.php',
        { 'action': 'get_events' },
        function (events) {
            events.forEach(function (event) {
                switch (event.type) {
                    case '2':
                        if (event.to === userPhone) {
                            showCard(event.from);
                        }
                        break;
                }
            });
        }
    );
}, 2000);
```

Чтобы проверить работу всплывающей карточки, создадим входящий звонок с номера 73430112233 на номер 101 с помощью диагностической утилиты Diagnostic.exe:

```
[events off]> Generate transfer 73430112233 101
```

На странице приложения должна незамедлительно появиться карточка:

![Карточка входящего звонка](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/incoming-popup.png)

Шаг 4. Умная переадресация
--------------------------

Чтобы воспользоваться функцией умной переадресации, нужно определить, какие звонки сотрудник хочет получать.

Будем считать, что все контакты, отображаемые на странице, закреплены за нашим сотрудником. Таким образом, условием для переадресации звонка будет наличие номера телефона звонящего в нашей базе контактов.

Функция для поиска в базе у нас уже есть, так что остаётся только добавить обработку событий трансфера:

```js
setInterval(function() {
    $.getJSON(
        'ajax.php',
        { 'action': 'get_events' },
        function (events) {
            events.forEach(function (event) {
                switch (event.type) {
                    case '1':
                        if (findByPhone(storage, event.from)) {
                            $.get('ajax.php', { 'action': 'transfer', call_id: event.callID, to: userPhone });
                        }
                        break;
                    
                    ...
                }
            });
        }
    );
}, 2000);
```

и добавить метод в файл ajax.php:

```php
function transfer(ProstieZvonki $pz, array $input) {
	$pz->transfer($input['call_id'], $input['to']);
}
```

Чтобы проверить функцию трансфера, отправим запрос с помощью диагностической утилиты:

```
[events off]> Generate incoming 73430112233
```

В консоли сервера мы должны увидеть, что приложение отправило запрос на перевод звонка на нашего пользователя:

```
Transfer Event: callID = 18467, to = 101
```

Шаг 5. История звонков
----------------------

Добавим на страницу ещё одну таблицу:

```html
<h2>История звонков</h2>
<table id="history" class="table table-bordered">
    <th width="10%">Направление</th>
    <th width="10%">Телефон</th>
    <th width="40%">Клиент</th>
    <th width="20%">Начало</th>
    <th width="20%">Продолжительность</th>
</table>
```

Чтобы заполнить таблицу информацией о совершённых звонках, нам нужно обрабатывать события истории звонков:

```js
pz.onEvent(function (event) {
    switch (true) {
        ...

        case event.isHistory():
            if (event.to === userPhone || event.from === userPhone) {
                appendCallInfo(event);
            }
            break;
    }
});
```
Займёмся реализацией функции `appendCallInfo`, работа которой заключается в том, чтобы отформатировать полученные данные о звонке и записать их в таблицу.

Для форматирования дат мы воспользуемся библиотекой [moment.js](http://momentjs.com/).

Распакуем библиотеку и файлы локализации в папку `js/momentjs` и подключим их:

```html
<script src="js/momentjs/moment.min.js"></script>
<script src="js/momentjs/lang/ru.js"></script>
```

Подключим локализацию:

```js
moment.lang('ru');
```

Сама функция будет выглядеть так:

```js
function appendCallInfo(event) {
    var direction = event.direction === '1' ? 'Исходящий' : 'Входящий',
        phone     = event.direction === '1' ? event.to : event.from,
        contact   = findByPhone(storage, phone),
        name      = contact ? contact.name : '',
        fromNow   = moment.unix(event.start).fromNow(),
        duration  = moment.duration(event.duration, "seconds").humanize();

    $('<tr></tr>')
        .append('<td>' + direction + '</td>')
        .append('<td>' + phone + '</td>')
        .append('<td>' + name + '</td>')
        .append('<td>' + fromNow + '</td>')
        .append('<td>' + duration + '</td>')
        .appendTo('#history');
}
```

Для проверки создадим два события истории с помощью диагностической утилиты:

```
[events off]> Generate history 101 73430112233 1378913389 1378913592 123 out
[events off]> Generate history 73430112211 101 1378914389 1378914592 250 in
```

![История звонков](https://github.com/vedisoft/js-sdk-tutorial/raw/master/img/history.png)

Ура!
----

Теперь наше приложение умеет показывать карточки со входящими звонками и переводить звонки прикреплённых клиентов, а пользователь может позвонить клиенту в один клик и посмотреть историю совершённых звонков.

Настройка заняла совсем немного времени, ведь так? : )

[архив]: https://github.com/vedisoft/php-sdk-tutorial/archive/master.zip
[php-sdk]: https://github.com/vedisoft/php-sdk/archive/master.zip