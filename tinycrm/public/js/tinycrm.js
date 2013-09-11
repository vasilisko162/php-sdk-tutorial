(function () {
    var userPhone   = '101';

    $('#button').on('click', function() {
        if ($(this).text() === 'Соединить') {
            $.get('ajax.php', { 'action': 'connect' });
        } else {
            $.get('ajax.php', { 'action': 'disconnect' });
        }
    });

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

    $('body').on('click', '.make-call', function() {
        $.get('ajax.php', { 'action': 'call', from: userPhone, to: $(this).text().trim() });
    });

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
                        case '2':
                            if (event.to === userPhone) {
                                showCard(event.from);
                            }
                            break;
                        case '4':
                            if (event.to.trim() === userPhone || event.from.trim() === userPhone) {
                                appendCallInfo(event);
                            }
                            break;
                    }
                });
            }
        );
    }, 2000);

    function sanitizePhone(phone)
    {
        return phone.replace(/\D/g, '').slice(-10);
    }

    function findByPhone(contacts, phone) {
        return contacts.filter(function (contact) {
            return sanitizePhone(contact.phone) === sanitizePhone(phone);
        }).shift();
    }

    function getNotyText(phone, name) {
        return '<span class="pz_noty_title">Входящий звонок</span>' +
            (name ? '<span class="pz_noty_contact">' + name + '</span>' : '') +
            '<span class="pz_noty_phone btn-link make-call">' + phone + '</span>' +
            '<span class="pz_noty_copyright">' +
                '<img src="img/pz.ico">' +
                '<a target="_blank" href="http://prostiezvonki.ru">Простые звонки</a>' +
            '</span>';
    }

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

    moment.lang('ru');

    function appendCallInfo(event) {
        var direction = event.direction === '1' ? 'Исходящий' : 'Входящий',
            phone     = event.direction === '1' ? event.to : event.from,
            contact   = findByPhone(storage, phone),
            name      = contact ? contact.name : '',
            fromNow   = moment.unix(parseInt(event.start)).fromNow(),
            duration  = moment.duration(parseInt(event.duration), "seconds").humanize();

        $('<tr></tr>')
            .append('<td>' + direction + '</td>')
            .append('<td>' + phone + '</td>')
            .append('<td>' + name + '</td>')
            .append('<td>' + fromNow + '</td>')
            .append('<td>' + duration + '</td>')
            .appendTo('#history');
    }
}());