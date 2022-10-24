<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$users = new Users;
$me = $users->current();

if (empty($me->is_admin)) {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

$ldap = LDAP::enabled();

$user = null;
$edit = $create = $delete = false;

if (!empty($_GET['edit']) && ($user = $users->getById((int) $_GET['edit']))) {
	$edit = true;
}
elseif (!empty($_GET['delete']) && ($user = $users->getById((int) $_GET['delete']))) {
	$delete = true;

	if ($user->id == $me->id) {
		die('You cannot delete your own account.');
	}
}
elseif (isset($_GET['create']) && !$ldap) {
	$create = true;
}

if ($create && !empty($_POST['create']) && !empty($_POST['login']) && !empty($_POST['password'])) {
	$users->create(trim($_POST['login']), trim($_POST['password']));
	header('Location: ' . WWW_URL . 'users.php');
	exit;
}
elseif ($edit && !empty($_POST['save']) && !empty($_POST['login'])) {
	if (!$ldap && empty($_POST['is_admin']) && $user->id == $me->id) {
		die("You cannot remove yourself from admins, ask another admin to do it.");
	}

	$data = array_merge($_POST, ['is_admin' => !empty($_POST['is_admin'])]);

	if ($ldap) {
		unset($data['is_admin'], $data['password'], $data['login']);
	}

	$users->edit($user->id, $data);

	if ($user->id == $me->id) {
		$_SESSION['user'] = $users->getById($me->id);
	}

	header('Location: ' . WWW_URL . 'users.php');
	exit;
}
elseif ($delete && !empty($_POST['delete'])) {
	$users->delete($user);
	header('Location: ' . WWW_URL . 'users.php');
	exit;
}

html_head('Manage users');

if ($create) {
	echo <<<EOF
	<form method="post" action="">
	<fieldset>
		<legend>Create a new user</legend>
		<dl>
			<dt><label for="f_login">Login</label></dt>
			<dd><input type="text" pattern="[a-z0-9_]+" name="login" id="f_login" required /></dd>
			<dt><label for="f_password">Password</label></dt>
			<dd><input type="password" name="password" id="f_password" /></dd>
			<dd><input type="submit" name="create" value="Create" /></dd>
		</dl>
	</fieldset>
EOF;
}
elseif ($edit) {
	$login = htmlspecialchars($user->login);
	$is_admin = $user->is_admin ? 'checked="checked"' : '';
	$quota = $user ? round($user->quota / 1024 / 1024) : DEFAULT_QUOTA;

	echo '<form method="post" action="">
	<fieldset>
		<legend>Edit user</legend>
		<dl>';

	if (!$ldap) {
		echo '
			<dt><label for="f_login">Login</label></dt>
			<dd><input type="text" pattern="[a-z0-9_]+" name="login" id="f_login" value="' . $login . '" required /></dd>
			<dt><label for="f_password">Password</label></dt>
			<dd><input type="password" name="password" id="f_password" /></dd>
			<dd>Leave empty if you don\'t want to change it.</dd>
			<dt><label for="f_is_admin">Status</label></dt>
			<dd><label><input type="checkbox" name="is_admin" id="f_is_admin" ' . $is_admin . ' /> Administrator</label></dd>';
	}

	echo '
			<dt><label for="f_quota">Quota</label></dt>
			<dd><input type="number" name="quota" step="1" min="0" value="' . $quota . '" required="required" size="6" /> (in MB)</dd>
			<!--<dd>Set to -1 to have unlimited space</dd>-->
			<dd><input type="submit" name="save" value="Save" /></dd>
		</dl>
	</fieldset>';
}
elseif ($delete) {
	$login = htmlspecialchars($user->login);
	echo <<<EOF
	<form method="post" action="">
	<fieldset>
		<legend>Delete user</legend>
		<h2>Do you want to delete the user "{$login}" and all their files?</h2>
		<dd><input type="submit" name="delete" value="Yes, delete" /></dd>
	</fieldset>
EOF;
}
else {
	echo '<p><a href="./" class="btn sm">&larr; Back</a></p>';

	if (!$ldap) {
		echo '<p><a href="?create" class="btn sm">Create new user</a></p>';
	}

	echo '
	<table>
	<thead>
		<tr>
			<th>User</th>
			<td>Quota</td>
			<td>Admin</td>
			<td></td>
		</tr>
	</thead>
	<tbody>';

	foreach ($users->list() as $user) {
		$used = Storage::getDirectorySize($user->path);

		printf('<tr>
			<th>%s</th>
			<td>%s used out of %s<br /><progress max="%d" value="%d"></progress></td>
			<td>%s</td>
			<td><a href="?edit=%d" class="btn sm">Edit</a> <a href="?delete=%d" class="btn sm">Delete</a></td>
		</tr>',
			htmlspecialchars($user->login),
			format_bytes($used),
			format_bytes($user->quota),
			$user->quota,
			$used,
			$user->is_admin ? 'Admin' : '',
			$user->id,
			$user->id
		);
	}

	echo '</tbody></table>';
}


html_foot();