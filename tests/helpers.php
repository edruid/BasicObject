<?php

/**
 * Returns true if $result contains the same things as $expected
 * Does not check order
 */
function compare_result($result, $expected) {
	for($i=0; $i<count($expected); ++$i) {
		$ok = false;
		foreach(array_keys($result) as $k) {
			if($result[$k] == $expected[$i]) {
				$ok = true;
				unset($result[$k]);
				break;
			}
		}
		if($ok == false) {
			echo "Expected to find model " . var_export($expected[$i], true) . "\n";
			return false;
		}
	}

	return empty($result);
}
