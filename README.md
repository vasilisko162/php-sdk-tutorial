Пример интеграции php-приложения с сервисом Простые Звонки
==========================================================

Мы начнём с веб-приложения, выводящего на экран список клиентов из базы данных, и добавим в него следующие функции:

- всплывающая карточка с информацией о клиенте при входящем звонке;
- исходящий звонок по клику на телефонный номер клиента.

Каждый шаг данного руководства представлен собственным тэгом в репозитории. Например, чтобы посмотреть версию кода, соответствующую первому шагу, нужно выполнить следующую команду:

```bash
git checkout 1
```

> Если вы не знакомы с системой контроля версий git - ничего страшного. Просто загрузите [архив] с файлами, распакуйте его и откройте в любимом редакторе.

**Обратите внимание:** данное приложение написано исключительно в образовательных целях. Для того, чтобы сделать код прощё и понятней, в нём намеренно опущены такие важные штуки, как обработка ошибок и граничных ситуаций, валидация и экранирование данных.

Шаг 0. Исходное приложение
--------------------------

Как я уже говорил, наше исходное приложение умеет показывать список клиентов. В качестве базы данных используется файл storage/contacts.csv. Прочитанные из файла строки отображаются в виде таблицы.

![Исходное приложение](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/tinycrm-origin.png)

Шаг 1. Настройка подключения к серверу
--------------------------------------

Скачаем и распакуем архив [php-sdk]. Скопируем Папки ProstieZvonki и TestSuite в наш проект.

Запустим тестовый сервер в режиме с отключенным шифрованием:

```bash
TestSuite/TestServer.exe -r
```

и подключимся к нему диагностической утилитой

```bash
TestSuite/Diagnostic.exe

[events off]> Connect localhost:10150 asd
Успешно начато установление соединения с АТС
```

Тестовое окружение настроено.

Добавим на страницу веб-приложения индикатор состояния соединения с сервером и кнопку, по нажатию на которую мы будем устанавливать соединение.

```html
<span id="button" class="btn pull-right">Соединить</span>
<span id="indicator" class="badge">Проверка соединения...</span>
```

Теперь наше приложение выглядит так:

![Индикатор состояния соединения](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/connection-indicator.png)

Приложение будет получать управлять соединением посредством ajax-запросов, поэтому нам нужно подготовить небольшой обработчик:

```php
require_once '../ProstieZvonki/ProstieZvonki.php';

$pz = ProstieZvonki::getInstance();

echo call_user_func($_GET['action'], $pz, $_GET);
```

Сохраним этот файл под именем public/ajax.php. Теперь, чтобы добавить новый тип ajax-запроса, нам нужно всего лишь объявить функцию с двумя аргументами: объектом класса ProstieZvonki (он будет выполнять всю работу по взаимодействию с сервером) и массив с GET-параметрами.

Добавим функции для подключения, отключения и получения информации о состоянии соединения:

```php
function is_connected(ProstieZvonki $pz) {
	return $pz->isConnected();
}

function connect(ProstieZvonki $pz) {
	$pz->connect(array(
		'client_id'     => '101',
		'client_type'   => 'SugarCRM',
		'host'          => 'localhost',
		'port'          => '10150',
		'proxy_enabled' => 'false',
	));
}

function disconnect(ProstieZvonki $pz) {
	$pz->disconnect();
}
```

Перейдём к настройки клиентской части приложения.

Добавим обработчик события кнопки. По нажатию кнопки будет выполнять запрос на подключение либо отключение соединения с сервером:

```js
$('#button').on('click', function() {
	if ($(this).text() === 'Соединить') {
		$.getJSON('ajax.php', { 'action': 'connect' });
	} else {
		$.getJSON('ajax.php', { 'action': 'disconnect' });
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
	var user_phone   = '223322';
	var client_phone = $(this).text().trim();

	$.getJSON('ajax.php', { 'action': 'call', from: user_phone, to: client_phone });
});
```

Кликнув на номер клиента, посмотрим на вывод тестового сервера:

```
Call event from CRM: src = 223322, dst = +7 (343) 0112233
```

Шаг 3. Всплывающая карточка входящего звонка
--------------------------------------------

Добавим очередную функцию в ajax.php:

```php
function get_events(ProstieZvonki $pz) {
	return json_encode($pz->getEvents());
}
```

На страницу поместим скрытый контейнер, который мы будем использовать в качестве всплывающей карточки:

```html
<div style="display: none;" class="alert alert-info"></div>
```

Осталось добавить функцию, которая будет раз в две секунды запрашивать список полученных событий:

```js
setInterval(function() {
	$.getJSON(
		'ajax.php',
		{ 'action': 'get_events' },
		function(data) {
			var events = data;

			for (var i in events) {
				if (events.hasOwnProperty(i)) {
					var event = events[i];

					if (event.type === "2") {
						$('.alert').text('Звонок с '+event.from+' на '+event.to).show();
					}
				}
			}
		}
	);
}, 2000);
```

Чтобы проверить работу всплывающей карточки, создадим входящий звонок с помощью диагностической утилиты:

```
Generate transfer 222 223344
```

На странице приложения должна незамедлительно появиться карточка:

![Карточка входящего звонка](https://github.com/vedisoft/php-sdk-tutorial/raw/master/img/incoming-popup.png)

[архив]: https://github.com/vedisoft/php-sdk-tutorial/archive/master.zip
[php-sdk]: https://github.com/vedisoft/php-sdk/archive/master.zip