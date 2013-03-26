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
	</div>
</body>
</html>
