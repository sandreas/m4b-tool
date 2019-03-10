<?php


namespace M4bTool\StringUtilities;


use ArrayAccess;
use Countable;
use SeekableIterator;

class Runes implements ArrayAccess, SeekableIterator, Countable
{

    const CARRIAGE_RETURN = "\r";
    const LINE_FEED = "\n";

    /** @var string[] */
    protected $runes = [];

    public function __construct($string = "")
    {
        if (!$this->isUtf8($string)) {
            throw new \InvalidArgumentException("Runes does only support UTF-8 strings");
        }

        $this->runes = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
    }


    private function isUtf8($string)
    {
        return preg_match("//u", $string);
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->runes[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->runes[$offset];
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        if (mb_strlen($value) !== 1) {
            throw new \InvalidArgumentException("Values must contain exactly one rune");
        }
        $this->runes[$offset] = $value;
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        array_splice($this->runes, $offset, 1);
    }

    /**
     * Return the current element
     * @link https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return current($this->runes);
    }

    /**
     * Checks if current position is valid
     * @link https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return current($this->runes) !== false;
    }

    /**
     * Rewind the Iterator to the first element
     * @link https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind()
    {
        reset($this->runes);
    }

    /**
     * Count elements of an object
     * @link https://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     * @since 5.1.0
     */
    public function count()
    {
        return count($this->runes);
    }

    /**
     * Seeks to a position
     * @link https://php.net/manual/en/seekableiterator.seek.php
     * @param int $position <p>
     * The position to seek to.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function seek($position)
    {
        $offset = $this->key();
        if ($position === $offset) {
            return;
        }

        if ($position < $offset) {
            for ($i = 0; $i < $offset - $position; $i++) {
                prev($this->runes);
            }
        }


        for ($i = 0; $i < $position - $offset; $i++) {
            $this->next();
        }
    }

    /**
     * Return the key of the current element
     * @link https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        return key($this->runes);
    }

    /**
     * Move forward to next element
     * @link https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next()
    {
        next($this->runes);
    }

    public function prev()
    {
        prev($this->runes);
    }

    public function offset($offset)
    {
        if ($offset < 0) {
            $offset = $this->count() + $offset;
        }
        $this->seek($offset);
        return $this->current();
    }

    public function __toString()
    {
        return implode("", $this->runes);
    }

    public function first()
    {
        return reset($this->runes);
    }

    public function last()
    {
        return end($this->runes);
    }


    public function slice($offset, $length = null)
    {
        return static::fromRunes(array_slice($this->runes, $offset, $length));
    }

    private static function fromRunes(array $runes)
    {
        $instance = new static;
        $instance->runes = $runes;
        $instance->rewind();
        return $instance;
    }
}