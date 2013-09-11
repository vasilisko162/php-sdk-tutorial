<?php require __DIR__ . '/../storage/storage.php'; ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>TinyCRM</title>
	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/bootstrap-responsive.min.css">
	<link rel="stylesheet" href="css/tinycrm.css">
</head>
<body>
	<div class="container">
		<h1>TinyCRM</h1>
		<h2>База клиентов</h2>
		<table id="contacts" class="table table-bordered">
			<?php foreach (Storage::get() as $contact): ?>
				<tr>
					<td><?= $contact['name'] ?></td>
					<td width="1%" nowrap>
					    <span title="Позвонить" class="btn-link make-call">
					        <?= $contact['phone'] ?>
					    </span>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<span id="button" class="btn pull-right">Соединить</span>
		<span id="indicator" class="badge">Проверка соединения...</span>
		<h2>История звонков</h2>
		<table id="history" class="table table-bordered">
			<th width="10%">Направление</th>
			<th width="10%">Телефон</th>
			<th width="30%">Клиент</th>
			<th width="30%">Начало</th>
			<th width="20%">Продолжительность</th>
		</table>
	</div>

	<script src="js/jquery.min.js"></script>
	<script src="js/noty/jquery.noty.js"></script>
	<script src="js/noty/layouts/bottomRight.js"></script>
	<script src="js/noty/themes/default.js"></script>
	<script src="js/momentjs/moment.min.js"></script>
	<script src="js/momentjs/lang/ru.js"></script>
	<script src="js/tinycrm.js"></script>

	<script>
		window.storage = <?php echo Storage::getJSON(); ?>;
	</script>
</body>
</html>
