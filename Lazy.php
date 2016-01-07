<?php
error_reporting(E_ALL);

/**
 * Class Lazy
 * 适合大量数据的流式数组操作类
 * Generator::Iterator
 * 提示：
 * Generator是一次性的，无法rewind
 * 同一个Iterator在多个循环中会互相影响
 * @author xiaofeng
 */
final class Lazy
{
    /* @var Generator $gen */
    private $gen;

    /* @var boolean $finished  标记完成状态 */
    private $finished = false;

    final private function __clone()  {}
    final private function __sleep()  {}
    final private function __wakeup() {}

    /**
     * Lazy constructor.
     * @param Generator $gen
     */
    final private function __construct(Generator $gen)
    {
        $this->gen = $gen;
    }

    /**
     * 得到内部Generator
     * @throws LogicException
     * @return Generator
     */
    public function __invoke()
    {
        if($this->finished) {
            throw new LogicException("Lazy stream has finished. (Can not rewind Generator)");
        }
        return $this->gen;
    }

    // 无效率，测试方法
    public function getIterator()
    {
        try {
            return new ArrayIterator(iterator_to_array($this->gen));
        } catch (Exception $e) {
            // FIXME
            throw  $e;
        }
    }

    /**
     * @return mixed
     */
    public function __toString()
    {
        return print_r(iterator_to_array($this->gen), true);
    }

    /**
     * @param Generator $gen
     * @return Lazy
     */
    public static function fromGenerator(Generator $gen)
    {
        return new self($gen);
    }

    /**
     * @param array $arr
     * @return Lazy
     */
    public static function fromArray(array $arr = [])
    {
        return new self(self::array2Generator($arr));
    }

    /**
     * @param Iterator $iter
     * @return Lazy
     */
    public static function fromIterator(Iterator $iter)
    {
        return new self(self::iter2Generator($iter));
    }

    /**
     * @param $start
     * @param $end
     * @param int $step
     * @return Lazy
     */
    public static function fromRange($start, $end, $step = 1)
    {
        return new self(self::range($start, $end, $step));
    }

    /**
     * @param $filename
     * @param string $mode
     * @return Lazy
     */
    public static function fromFile($filename)
    {
        return new self(self::genReadline($filename));
    }


    /**
     * array map ( [callable $callback = null, , array|Generator $... ] )
     * @param callable|null $callback
     * @param array|Generator $v,...
     * @return $this
     */
    public function map()
    {
        $args = func_get_args();
        if(count($args) < 1) {
            throw new InvalidArgumentException("Argument 1 must be callable or null");
        }

        $callback = array_shift($args);
        if(!is_callable($callback) && $callback !== null) {
            throw new InvalidArgumentException("Argument 1 must be callable or null");
        }

        $nArrays = count($args);
        if($callback === null && $nArrays === 0) {
            return $this;
        }

        if($nArrays > 0) {
            foreach($args as $i => $arg) {
                if(!is_array($arg) && !($arg instanceof Iterator)) {
                    throw new InvalidArgumentException(sprintf("Argument %d should be an array or a Iterator", $i + 2));
                }
                if(is_array($arg)) {
                    $args[$i] = self::array2Generator($arg);
                }
            }
        }

        /* @var []Generator $args */
        if($nArrays === 0) {
            // 只传callback的处理
            $this->gen = self::genOneCbArgMap($this->gen, $callback);
        } else {
            // 传额外参数的处理
            $this->gen = self::genMultiGenMap($this->gen, $args, $callback);
        }

        return $this;
    }

    /**
     * mixed reduce ( callable $callback [, mixed $initial = NULL ] )
     * @param callable $callback 比array_reduce 添加第三个参数, $k
     * @param null $initial
     * @return $mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        $this->finished = true;
        $carry = $initial;
        foreach($this->gen as $k => $v) {
            $carry = $callback($carry, $v, $k);
        }
        return $carry;
    }

    /**
     * array filter ( [callable $callback [, int $flag = 0 ]] )
     * @param callable|null $callback
     * @param int $flag
     * @return $this
     */
    public function filter(callable $callback = null, $flag = 0)
    {
        $this->gen = self::genFilter($this->gen, $callback, $flag);
        return $this;
    }

    /**
     * 将Generator转化为array
     * 提示：Generator只能遍历一次，所以也只能toArray一次
     * @param bool $useKeys
     * @throws LogicException
     * @return array
     */
    public function toArray($useKeys = true)
    {
        if($this->finished) {
            throw new LogicException("Lazy stream has finished. (Can not rewind Generator)");
        }
        $this->finished = true;
        return iterator_to_array($this->gen, $useKeys);
    }

    /**
     * @param $filename
     * @param string $mode a:append w:write ...
     * @param callable|null $callback
     */
    public function toFile($filename, $mode = "w", callable $callback = null)
    {
        $f = @fopen($filename, $mode);
        if(!$f) {
            throw new InvalidArgumentException("open $filename fail");
        }
        $this->finished = true;
        foreach($this->gen as $k => $v) {
            if($callback) {
                $v = $callback($v, $k);
            }
            fwrite($f, $v);
        }
        fclose($f);
    }

    /**
     * @param Generator $gen
     * @param callable $callback
     * @return Generator
     */
    private static function genOneCbArgMap(Generator $gen, callable $callback)
    {
        foreach($gen as $k => $v) {
            yield $k => $callback($v);
        }
    }

    /**
     * 多Generator : [$gen, ...$generators] 合并, 类似MultipleIterator，参考array_map源码实现
     * @param Generator $gen 与array_map的第二个参数类似，最外层循环的Iterator, 传入$this->gen
     * @param array $generators []Generaotrs
     * @param callable|null $callback
     * @return Generator
     * @desc 提示:
     * callback参数数量:
     *  若存在$callback, 则只取Generator的value做callback的参数
     *  callback接受参数的个数必须与传入的Generator参数数量相同, 即 callback::func_get_args() === count([$gen]) + count($generators)
     *
     * 与array_map比较:
     * 1. 键:
     * array_map:
     *  废弃数组原有key,使用自增key
     * self::map:
     *  只使用$gen的key作为最终Generator的key, 废弃$generators内多个Gen的key
     * 2. 生成结果的Count
     * array_map:
     *  array_map 参考php内部实现C源码 https://github.com/php/php-src/blob/36a9b3454814766f81821392843a064efff60b5d/ext/standard/array.c#L5291
     *  取消array_map第2~n数组参数取最大其中maxlen，其余补全null逻辑
     *  修改成以iterator_count($gen)长度为基准生成Generator
     *  节省iterator_count遍历对象调用，从而可以用map接受iterator_count非常大的Generator对象做参数
     * self::map：
     *  $generators内多个Iterator的count小于$gen的count,填充null,小于$gen的count,丢弃
     * @TODO 重构为如下签名, 不区分第一个Generator与其他Generator
     * public static function genMultiGenMap(array $generators, callable $callback = null)
     */
    private static function genMultiGenMap(Generator $gen, array $generators, callable $callback = null)
    {
        /* @var []Generator $generators */
        foreach($gen as $k => $v) {
            $keys = [$k];
            $values = [$v];

            /* @var Generator $generator */
            foreach($generators as $generator) {
                if($generator->valid()) {
                    $keys[] = $generator->key(); // 暂未使用
                    $values[] = $generator->current();
                    $generator->next();
                } else {
                    $keys[] = null;
                    $values[] = null;
                }
            }
            if($callback) {
                $values = call_user_func_array($callback, $values);
            }
            yield $k => $values;
        }
    }

    /**
     * array filter ( Generator $gen [, callable $callback [, int $flag = 0 ]] )
     * @param Generator $gen
     * @param callable|null $callback
     * @param int $flag
     * @return Generator
     */
    private static function genFilter(Generator $gen, callable $callback = null, $flag = 0) {
        foreach($gen as $k => $v) {
            if($callback) {
                $cbArgs = [$v];
                switch((int)$flag) {
                    case 0:
                        break;
                    case ARRAY_FILTER_USE_KEY:
                        $cbArgs = [$k];
                        break;
                    case ARRAY_FILTER_USE_BOTH:
                        $cbArgs = [$v, $k];
                        break;
                    default:
                }
                $isYield = (bool)call_user_func_array($callback, $cbArgs);
            } else {
                $isYield = (bool)$v;
            }

            if($isYield) {
                yield $k => $v;
            }
        }
    }

    /**
     * range(mixed low, mixed high[, int step])
     * @param int $start
     * @param int $end
     * @param int $step positive
     * @return Generator
     */
    private static function range($start, $end,  $step = 1)
    {
        if(!is_numeric($start)) {
            $start = 0;
            // throw new InvalidArgumentException("Argument 1 should to number");
        }
        if(!is_numeric($end)) {
            $end = 0;
            // throw new InvalidArgumentException("Argument 1 should to number");
        }

        if(!is_numeric($step) || $step <= 0) {
            $step = 1;
        }

        if(($start <= $end)) {
            while ($start <= $end) {
                yield $start;
                $start += $step;
            }
        } else if(($start >= $end)) {
            while ($start >= $end) {
                yield $start;
                $start -= $step;
            }
        }
    }

    /**
     * @param array $arr
     * @return Generator
     */
    private static function array2Generator(array $arr)
    {
        foreach($arr as $k => $v) {
            yield $k => $v;
        }
    }

    /**
     * @param Iterator $iter
     * @return Generator
     */
    private static function iter2Generator(Iterator $iter)
    {
        // $iter->rewind();
        foreach($iter as $k => $v) {
            yield $k => $v;
        }
    }

    private static function genReadline($filename, $buffer = 4096)
    {
        $f = @fopen($filename, "r");
        if(!$f) {
            throw new InvalidArgumentException("open $filename fail");
        }
        while(!feof($f)) {
            yield fgets($f, $buffer);
        }
        fclose($f);
    }
}