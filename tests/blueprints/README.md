Introduction
======
Blueprints are a handy way to create models for your tests.

Usage
========

Blueprints
----------
For each class you want to have a blueprint, create a file {class_name}.json here.
(ex for class "User" create "User.json")

A simple blueprint might look like this:

	_blueprints/User.json_

	{
		default: {
			username: "Foobar",
			real_name: "Foo Bar"
		}
	}

You can now create a user with:

	Blueprint::make('User');

The "default" is the name of the blueprint. You should always have at least a default blueprint for each model,
but you can create more blueprints with different names, to use in different situations, eg:

	{
		default: {
			...
		},
		foobar: {
			...
		},
		baz: {
			...
		}

To use a named blueprint instead of the default, send the name as a argument to make:

	Blueprint::make('User', 'foobar');

You can also override the value set by the blueprint by sending new values as a array to make:

	Blueprint::make('User', array('username' => 'Baz'));

By default make commits the model to the database, but you can tell it not to:

	Blueprint::make('User', false);

You can use all or none of the optional arguments (name, value, commit) to make at any call,
and they can be in any order, but this order is recommended:

	Blueprint::make('User', {name}, {values}, {commit})

Unique Attributes
---------------

For attributes that need to be unique you can add #{sn} to the value to get a unique serial number:

	{
		default: {
			username: "User-#{sn}",
			...
		}
	}

The sn remains the same for the whole object

Reusing attribute values
-----------------

You can also use #{other_field_name} which will yield the value of that field. Note that
all fields are set in the order they are defined, so you can only refeere to a previously declared value:

	{
		default: {
			username: "User-#{sn}",
			foobar: "#{username}"
		}
	}

Associations
---------------

If your object have associations with other objects, you can do like this:

	{
		default: {
			Model2: {}
		}
	}

This will set the field Model2 to a new blueprint instance of the class Model2.
You can also specify attributes:
* blueprint: The name of the blueprint to use
* class: The name of the other class
* values: Values to set

Any other key/value-pair will be used as values as well, so this works:

	other_model: {
		blueprint: 'test',
		class: "Model2",
		derp: "Foobar"
	}

Thus you only need to use the values field if you need to specify a field named blueprint, class or values.

JSON modifications
-----------
Unfortunatly foo: "bar" is not correct json syntax, the correct way to do this would be "foo": "bar",
but it's just annoying to have to write the extra quotation-marks for each attributes.

To prevent errors this is though only done if the attribute is on the beginning of the row, so this works:

	foo: "bar",
	baz: "derp"

But this will give a syntax error:

	foo: {class: "bar", blueprint: "derp"}


