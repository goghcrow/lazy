<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "Lazy.php";

ini_set('memory_limit', '2048M');
define("ITER_MAX", 1e7); // 10000000


function test(Closure $func) {
    $start = microtime(true);
    $func();
    echo "cost_time: " . round((microtime(true) - $start), 2) . "s" . PHP_EOL;
    // echo "cost_mem: " . round(memory_get_usage() / 1024 / 1024, 2) . "M" . PHP_EOL;
    echo "peak_mem: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . "M" . PHP_EOL;
}

$add1 = function($v) { return $v + 1; };
$sum = function($carry, $v) { return $carry + $v; };
$existSql = function($v) { return (strpos($v, "SQL") !== false); };
$dummyFunc = function($v) { return $v; };

if(!isset($argv[1])) {
    return;
}

switch($argv[1]) {
    case "funcsum":
        test(function() use($add1, $sum) {
            echo "func result: " . array_reduce(array_map($add1, range(0, ITER_MAX)), $sum) . PHP_EOL;
        });
        break;

    case "lazysum":
        test(function() use($add1, $sum) {
            echo "lazy result: " . Lazy::fromRange(0, ITER_MAX)->map($add1)->reduce($sum) . PHP_EOL;
        });
        break;

    case "itersum":
        test(function() use($add1, $sum, $dummyFunc) {
            $result = 0;
            $tmp = null;
            for($i = 0; $i <= ITER_MAX; $i++) {
//                $result += $i + 1;
                $result += $add1($i);
                $tmp = $dummyFunc($tmp);
            }
            echo "iter result: " . $result . PHP_EOL;
        });
        break;

    case "lazyfile":
        test(function() use($existSql) {
            Lazy::fromFile("db.log")->filter($existSql)->toFile("db_filter.log");
        });
        break;

    case "memfile":
        test(function() use($existSql) {
            $buffer = [];
            foreach(file("db.log") as $v) {
                if($existSql) {
                    $buffer[] = $v;
                }
            }
            file_put_contents("db_filter.log", implode($buffer));
        });
        break;
}