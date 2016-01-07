<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "Lazy.php";


///*
$ten1 = Lazy::fromRange(0, 9)->getIterator();
$ten2 = Lazy::fromRange(0, 9)->getIterator();
$ten3 = Lazy::fromRange(0, 9)->getIterator();
$addOne = function($v, $x1, $x2) { return $v + $x1 + $x2;};
echo Lazy::fromIterator($ten1)->map($addOne, $ten2, $ten3);
$ten2->rewind();
$ten3->rewind();
echo Lazy::fromIterator($ten1)->map($addOne, $ten2, $ten3)->reduce(function($carry, $v, $k) { return $carry + $v; });
//*/



///*
echo Lazy::fromRange(0, 9)->map(function($v) { return $v+1; });
//*/



///*
echo Lazy::fromRange(0, 9)->map(null, [1,2,3], [4,5,6]);
//*/



///*
echo Lazy::fromRange(0, 10)
    ->map(function($v) { return $v+1; })
     ->filter(function($v) { return ($v & 1);});
echo Lazy::fromRange(0, 10)
    ->map(function($v) { return $v+1; })
    ->filter(function($k) { return ($k === 0);}, ARRAY_FILTER_USE_KEY);
echo Lazy::fromRange(0, 10)
    ->map(function($v) { return $v+1; })
     ->filter(function($v, $k) { return $k === 0 && $v === 1;}, ARRAY_FILTER_USE_BOTH);
//*/



///*
$input = [
    "x0" => 0,
    "x1" => 1,
    "x2" => 2,
    "x3" => 3,
    "x4" => 4,
    "x5" => 5,
];
echo Lazy::fromArray($input)
    ->map(function($v) { return $v+1; })
    ->filter(function($v) { return ($v & 1);});

echo Lazy::fromArray($input)
    ->map(function($v) { return $v+1; })
    ->filter(function($v) { return ($v & 1);})
    ->reduce(function($carry, $v) { return $carry + $v; }) . PHP_EOL;

echo Lazy::fromArray($input)
    ->map(function($v) { return $v+1; })
    ->filter(function($k) { return ($k === "x0");}, ARRAY_FILTER_USE_KEY);
echo Lazy::fromArray($input)
    ->map(function($v) { return $v+1; })
    ->filter(function($v, $k) { return $k === "x0" && $v === 1;}, ARRAY_FILTER_USE_BOTH);
//*/


/*
Lazy::fromFile("db.log")->filter(function($v) {
    return (strpos($v, "SQL") !== false);
})->toFile("db_filter.log");
//*/


/*
$ten = Lazy::fromRange(0, 9)->getIterator();
$addOne = function($v, $x1, $x2) { return $v + $x1 + $x2;};
// 迭代器的使用错误：
// 同一个迭代器传入，foreach速递 *= 迭代器个数
echo Lazy::fromIterator($ten1)->map($addOne, $ten, $ten);
exit;
//*/
