<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>TinyCRM</title>
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/bootstrap-responsive.min.css">
</head>
<body>
	<div class="container">
		<h1>TinyCRM</h1>
		<h2>База клиентов</h2>
		<table id="contacts" class="table table-bordered">
			<?php foreach (file('../storage/contacts.csv') as $contact): ?>
				<?php $contact = explode(',', $contact); ?>
				<tr>
					<td><?= $contact[0] ?></td>
					<td width="1%" nowrap><?= $contact[1] ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<span id="button" class="btn pull-right">Соединить</span>
		<span id="indicator" class="badge">Проверка соединения...</span>
	</div>

	<script src="js/jquery.min.js"></script>
	<script>
	(function(){
		$('#button').on('click', function() {
			if ($(this).text() === 'Соединить') {
				$.getJSON('ajax.php', { 'action': 'connect' });
			} else {
				$.getJSON('ajax.php', { 'action': 'disconnect' });
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
	}())
	</script>
</body>
</html>
