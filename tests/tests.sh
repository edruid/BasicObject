echo "Running tests without cache\n"
phpunit --bootstrap "no_cache.php"

ret=$?

if [ $ret -ne 0 ]; then
	exit $ret
fi


echo "\n----------------------\n"
echo "Running tests with cache\n"
phpunit --bootstrap "with_cache.php"
exit $?
