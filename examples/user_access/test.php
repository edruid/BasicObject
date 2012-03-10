<?php
require "includes.php";

$db = new Mysqli("127.0.0.1", "bo_example", "fubar", "bo_example");

BasicObject::$output_htmlspecialchars = false;

$user = new User();
$user->username = 'bob';
$user->given_name = 'Robert';
$user->surname = 'Marley';
$user->sex = 'male';
$user->birthdate = '1945-02-06';
$user->email = 'bob@nodomain.nowhere';
$user->commit();
$id = $user->id;
unset($user);

$user = User::from_id($id);
assert($user->surname == 'Marley');

unset($user);

$user = new User();
$user->username = "Foo'bar\" <b>baz";
$user->commit();

$id = $user->id;
unset($user);

BasicObject::$output_htmlspecialchars = false;
$user = User::from_id($id);
assert($user->username == "Foo'bar\" <b>baz");

BasicObject::$output_htmlspecialchars = true;
$user = User::from_id($id);
assert($user->username == "Foo&#039;bar&quot; &lt;b&gt;baz");

echo "Done";
