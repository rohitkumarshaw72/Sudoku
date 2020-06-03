<?php


###########################################################
#               various auxiliary functions               #
###########################################################
/*
 * Calculate indices of duplicate values
 * but only if value != NULL and value isn't an array
 */
function arrayKeysDup (array $arr):array {
    $res   = [];
    $arr2  = [];
    // Loop through the array
    foreach ($arr as $key => $value) {
        if ($value && !is_array($value)) {
            // If $value is already in $arr2 (as key),
            // $value is a dublicate, so add the indices
            // to result
            if (isset($arr2[$value])) {
                // Add previous index, if not already in result
                if (!in_array($arr2[$value], $res)) {
                    $res[] = $arr2[$value];
                }
                // Add current index
                $res[] = $key;
            } else {
                // Add current value as key to $arr2
                $arr2[$value] = $key;
            }
        }
    }
    return $res;
}
    
/*
 * Returns the keys (indices) of NULL values.
 * Keys of $arr are preserved.
 */
 function arrayKeysNoVal ($arr):array {
    $res = [];
    foreach ($arr as $key => $value) {
        if ($value === 0 ||
            !is_scalar($value)) {
            $res[$key] = $key;
        }
    }
    return $res;
}
    
/*
 * Returns the keys (indices) of $arr whom are itself an array.
 */
 function arrayKeysArray ($arr):array {
    $res = [];
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            $res[] = $key;
        }
    }
    return $res;
}
    
/*
 * Returns how many times a value occurs in an array. Search sub
 * arrays recursively.
 */
function arrayValCountRecursiv ($val, array $arr):int {
    $count = 0;
    foreach ($arr as $a) {
        if ($val == $a) {
            $count++;
        }
        if (is_array($a)) {
            $count += arrayValCountRecursiv($val, $a);
        }
    }
    return $count;
}



###########################################################
#                    class definitions                    #
###########################################################
/*
 * SudokuView Class
 */
abstract class SudokuView implements IteratorAggregate,
                                     ArrayAccess,
                                     Countable {
    
    protected $arr = [];       // Data
    protected $blockSizeH;     // Length of a block
    protected $blockSizeV;     // Hight of a block
    protected $size;           // Side length of the sudoku
    
    /*
     * Constructor
     */
    public function __construct (int $blockSizeH, int $blockSizeV) {
        $this->blockSizeH = $blockSizeH;
        $this->blockSizeV = $blockSizeV;
        $this->size       = $blockSizeH * $blockSizeV;
    }
    
    /*
     * Clone
     * Make $this->arr a copy of $orginalObject->arr.
     * After the clone() this is a reference to the oginal array,
     * ??? because offsetGet() returns a reference.! ???
     */
    public function __clone () {
        // Maybe there is a nother way...
        $arr = $this->arr;
        unset($this->arr);
        $this->arr = [];
        
        $len = $this->size ** 2;
        for ($i = 0; $i < $len; ++$i) {
            if (is_scalar($arr[$i])) {
                $this->arr[$i] = $arr[$i];
            } else {
                $this->arr[$i] = 0;
            }
        }
    }
    
    /*
     * Implements IteratorAggregate
     * Yields the elements form $this->arr, when object is used in
     * foreach loop.
     */
    public function getIterator () {
        yield from $this->arr;
    }
    
    /*
     * Implements ArrayAccess
     * Returns the element with the index $offset, when object ist used as
     * an array (e. g. $obj[$offset]).
     */
    public function offsetGet ($offset) {
        return $this->arr[$offset] ?? NULL;
    }
    
    /*
     * Implements ArrayAccess
     * Returns TRUE if $offset exits, otherwise FALSE.
     */
    public function offsetExists ($offset):bool {
        return isset($this->arr[$offset]);
    }
    
    /*
     * Implements ArrayAccess
     * Unsets the element with the index $offset.
     */
    public function offsetUnset($offset) {
        unset($this->arr[$offset]);
    }
    
    /*
     * Implements ArrayAccess
     * Sets the element with index $offset to $value.
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->arr[] = $value;
        } else {
            $this->arr[$offset] = $value;
        }
    }
    
    /*
     * Implements Countable
     * Return the count of elements.
     */
    public function count () {
        return count($this->arr);
    }
    
    /*
     * Getter
     */
    public function getSize       ():int { return $this->size; }
    public function getBlockSizeH ():int { return $this->blockSizeH; }
    public function getBlockSizeV ():int { return $this->blockSizeV; }
    
    /*
     * Returns which row the field with $gameIndex belongs to.
     */
    protected function rowN (int $gameIndex):int {
        return (int) ($gameIndex / $this->size);
    }
    
    /*
     * Returns which column the field with $gameIndex belongs to.
     */
    protected function colN (int $gameIndex):int {
        return $gameIndex % $this->size;
    }
        
    /*
     * Returns which block the field in $rowN and $colN belongs to.
     */
    protected function blockN (int $rowN, int $colN):int {
        return ((int) ($colN / $this->blockSizeH)) +
               $this->blockSizeV *
               ((int) ($rowN / $this->blockSizeV));
    }
    
    /*
     * Returns the game index.
     * $part      may be 'rows', 'cols' or 'blocks'
     * $partN     part number (counted from 0)
     * $partIdx   index in the part (counted from 0)
     */
    protected function gameIndexFromPart (string $part,
                                          int    $partN,
                                          int    $partIdx): int
    {
        switch ($part) {
            case 'rows':
                return $this->gameIndexFromRow($partN, $partIdx);
            case 'cols':
                return $this->gameIndexFromCol($partN, $partIdx);
            case 'blocks':
                return $this->gameIndexFromBlock($partN, $partIdx);
        }
        return -1;
    }
        
    /*
     * Returns the game index.
     * $rowN      row number (counted from 0)
     * $rowIdx    index in row (counted from 0)
     */
    protected function gameIndexFromRow (int $rowN, int $rowIdx):int {
        return $rowN * $this->size + $rowIdx;
    }

    /*
     * Returns the game index.
     * $colN      column number (counted from 0)
     * $colIdx    index in column (counted from 0)
     */
    protected function gameIndexFromCol (int $colN, int $colIdx):int {
        return $colIdx * $this->size + $colN;
    }
    
    /*
     * Returns the game index.
     * $blockN      block number (counted from 0)
     * $blockIdx    index in block (counted from 0)
     */
    protected function gameIndexFromBlock (int $blockN, int $blockIdx):int {
        $blockRowN   = (int) ($blockN / $this->blockSizeV);
        $rowInBlockN = (int) ($blockIdx / $this->blockSizeH);
        $rowN        = $blockRowN * $this->blockSizeV + $rowInBlockN;
            
        $blockColN   = $blockN % $this->blockSizeV;
        $colInBlockN = $blockIdx % $this->blockSizeH;
        $colN        = $blockColN * $this->blockSizeH + $colInBlockN;
            
        return $rowN * $this->size + $colN;
    }


}
    
    
    
/*
 * Sudoku Base Class
 */
abstract class Sudoku extends SudokuView {
        
    protected $rows;                // The Sudoku viewed by rows
    protected $cols;                // The Sudoku viewed by columns
    protected $blocks;              // The Sudoku viewed by blocks
    protected $allowedValues = [];  // Array with allowed values
        
    /*
     * Constructor
     */
    public function __construct (array $game,
                                 int   $blockSizeH = 3,
                                 int   $blockSizeV = 3) {
                                     
        parent::__construct($blockSizeH, $blockSizeV);
        
        $this->allowedValues = str_split(substr('123456789ABC',
                                                0,
                                                $this->size));
        
        // Test if game size and blockSize match...
        // Test game size...
        if (count($game) != $this->size ** 2 ||
            $this->size > count($this->allowedValues)) {
            throw new Exception("Invalid game size!");
        }
        
        // Test if $game contains only values from $this->allowed values
        // and 0.
        if (array_diff($game, $this->allowedValues, [0])) {
            throw new Exception("Illegal values in game!");
        }
        
        $this->arr = $game;
        // initialize $this->rows, $this->cols and $this $blocks
        $this->init();
    }
    
    /*
     * Clone
     */
    public function __clone () {
        parent::__clone();
        // initialize $this->rows, $this->cols and $this->blocks
        // becaus these are object. Clone() copies only the referece but
        // new objects are needed.
        $this->init();
    }
        
    /*
     * Output for debugging...
     * Returns the sudoku as ASCII string, which could be printed 
     * in the console.
     */
    public function __toString ():string {
        $str = ''; 
        $separator = '|';
        $lineSeparator = str_repeat(
            '+' . str_repeat('--', $this->blockSizeH) . '-',
            $this->blockSizeV
            ) . '+' . PHP_EOL;
                 
        foreach ($this as $key => $value) {
            if ($key % ($this->size * $this->blockSizeV) == 0) {
                $str .= $lineSeparator;
            }            
            if ($key % $this->blockSizeH == 0) {
                $str .= $separator . ' ';
            }           
            $str .= $value && is_scalar($value) ? "$value " : "  ";           
            if (($key + 1) % $this->size == 0) {
                $str .= $separator . PHP_EOL;
            }
        }
        $str .= $lineSeparator;
                    
        return $str;
    }
    
    /*
     * Output as html table
     */
    public function echoHtmlTable () {
        echo '<table>';
        
        for ($rowN = 0; $rowN < $this->blockSizeV; ++$rowN) {
            echo '<colgroup>';
            for ($colN = 0; $colN < $this->blockSizeH; ++$colN) {
                echo '<col>';
            }
            echo '</colgroup>';
        }
        
        foreach ($this->arr as $idx => $field) {
            if ($idx % $this->size == 0) {
                if ($idx) {
                    echo '</tr>';
                }
                if ($idx % ($this->size * $this->blockSizeV) == 0) {
                    if ($idx) {
                        echo '</tbody>';
                    }
                    echo '<tbody>';
                }
                echo '<tr>';
            }
            $this->echoField($idx, $field);
        }
        
        echo '</tr></tbody></table>';
    }
    
    /*
     * echo $field
     */
    protected function echoField ($idx, $field) {
        if ($field && is_scalar($field)) {
            echo "<td class=\"field\">$field</td>";
        } else {
            echo '<td></td>';
        }
    }
    
    /*
     * Create the rows, cols and block view.
     * Needs to be called by the constructor and when the object is cloned.
     */
    private function init () {
        // Create row view.
        $this->rows = new class ($this->arr,
            $this->blockSizeH,
            $this->blockSizeV) extends SudokuView {
                public function __construct (&$game,
                    $blockSizeH,
                    $blockSizeV) {
                        parent::__construct($blockSizeH, $blockSizeV);
                        
                        $rowN = -1;
                        foreach ($game as $key => &$value) {
                            $rowN = $this->rowN($key);
                            $this->arr[$rowN][] = &$value;
                        }
                }
        };
        // Create col view.
        $this->cols = new class ($this->arr,
            $this->blockSizeH,
            $this->blockSizeV) extends SudokuView {
                public function __construct (&$game,
                    $blockSizeH,
                    $blockSizeV) {
                        parent::__construct($blockSizeH, $blockSizeV);
                        
                        $colN = 0;
                        foreach ($game as $key => &$value) {
                            $colN = $this->colN($key);
                            $this->arr[$colN][] = &$value;
                        }
                }
        };
        // Create block view.
        $this->blocks = new class ($this->arr,
            $this->blockSizeH,
            $this->blockSizeV) extends SudokuView {
                public function __construct (&$game,
                    $blockSizeH,
                    $blockSizeV) {
                        parent::__construct($blockSizeH, $blockSizeV);
                        
                        $blockN = 0;
                        foreach ($game as $key => &$value) {
                            $rowN   = $this->rowN($key);
                            $colN   = $this->colN($key);
                            $blockN = $this->blockN($rowN, $colN);
                            $this->arr[$blockN][] = &$value;
                        }
                }
        };
    }
}



/*
 * Sudoku Validate
 * Validates if $game is a valid sudoku.
 * The Sudoku is validated on object creation.
 */
class SudokuValidator extends Sudoku {
    
    protected $failedParts = [      // Failed parts
        'rows'   => [],             // Failed rows
        'cols'   => [],             // Failed cols
        'blocks' => []              // Failed blocks
    ];
    protected $failedIndices = [];  // Array with failed indices of $game
    
    /*
     * Constructor
     */
    public function __construct (array $game,
                                 int   $blockSizeH = 3,
                                 int   $blockSizeV = 3) {
        
        parent::__construct($game, $blockSizeH, $blockSizeV);
        $this->validate();
    }
    
    /*
     * Getter
     */
    public function getFailedParts ():array  { return $this->failedParts; }
    public function getFailedIndices():array { return $this->failedIndices; }
       
    /*
     * Return TRUE if Sodoku is valid, otherwise FALSE.
     */
    public function status ():bool {
        return !$this->failedIndices;
    }
    
    /*
     * Override offestSet to only allow $this->allowedValues and 0.
     * when an element is set like an array (e. g. $sudoku[$index] = value).
     */
    public function offsetSet($offset, $value) {
        if (is_int($offset) &&
            $offset >= 0 && 
            $offset < count($this) &&
            (in_array($value, $this->allowedValues) || $value ===0)) {
                $this->arr[$offset] = $value;
        } else {
            trigger_error("Invalid parameter to offsetSet!", E_USER_WARNING);
        }
        $this->validate();
    }
    
    /*
     * Validate the Sudoku and set $this->failedParts and
     * $this->failedIndices.
     */
    protected function validate ():bool {
        
        // clear vars
        $this->failedParts = [
            'rows'   => [],
            'cols'   => [],
            'blocks' => []
        ];
        $this->failedIndices = [];
        
        foreach (['rows', 'cols', 'blocks'] as $kind) {
            foreach ($this->$kind as $partN => $part) {
                $dupVals = arrayKeysDup($part);
                if ($dupVals) {
                    $this->failedParts[$kind][$partN] = $partN;
                    foreach ($dupVals as $idx) {
                        $gameIndex = $this->gameIndexFromPart($kind, $partN, $idx);
                        $this->failedIndices[$gameIndex]=$gameIndex;
                    }
                }
            }
        }
        return !$this->failedIndices;
    }

    /*
     * echo $field
     */
    protected function echoField ($idx, $field) {
        $rowN   = $this->rowN($idx);
        $colN   = $this->colN($idx);
        $blockN = $this->blockN($rowN, $colN);
        
        echo '<td class="field';
        
        if (isset($this->failedIndices[$idx])) { echo ' failed'; }
       
        if (isset($this->failedParts['rows'][$rowN]) ||
            isset($this->failedParts['cols'][$colN]) ||
            isset($this->failedParts['blocks'][$blockN]))
        { echo ' failed-part'; }
        
        echo '">';
        if ($field && is_scalar($field)) { echo $field; }
        echo '</td>';
    }
    
}
?>



<!-- Examples / Test -->
<!DOCTYPE html>
<html>

<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Sudoku</title>
<style type="text/css">
    h1 {
        text-align: center;
    }
    
    pre {
        border: thin solid black;
        padding: 1vmin;
        max-width: 80em;
        margin: 5vmin auto;
    }
    
    table {
        border-collapse: collapse;
        font-family: monospace, monospace;
        margin: 5vmin auto;
    }

    th, td {
        border: thin dotted black;
    }
    
    table colgroup, tbody {
        border: medium solid black;
    }
    
    td {
        width: 9vmin;
        height: 9vmin;
        font-size: 7vmin;
        text-align: center;
        vertical-align: middle;
    }
            
    .failed {
        color: red;
    }
    
    .failed-part {
        background: gray;
    }

</style>
</head>

<body>
    <h1>Sudoku Validator</h1>
    <pre><code>$game = [
    0, 0, 0,  0, 0, 1,  0, 9, 0,
    0, 0, 9,  3, 0, 0,  7, 0, 4,
    0, 0, 0,  0, 0, 0,  0, 0, 5,
        
    0, 0, 0,  0, 0, 6,  0, 0, 1,
    1, 0, 0,  0, 0, 7,  4, 6, 8,
    0, 0, 0,  0, 0, 0,  5, 0, 0,
            
    5, 7, 0,  0, 0, 8,  3, 0, 0,
    0, 9, 3,  6, 0, 5,  0, 1, 7,
    8, 0, 6,  1, 0, 0,  9, 0, 0
];
                
$sudoku = new SudokuValidator($game);
$sudoku[30] = 3;   // row = 4, column = 4
$sudoku->echoHtmlTable();</code></pre>
        
    <?php
        $game = [
            0, 0, 0,  0, 0, 1,  0, 9, 0,
            0, 0, 9,  3, 0, 0,  7, 0, 4,
            0, 0, 0,  0, 0, 0,  0, 0, 5,
        
            0, 0, 0,  0, 0, 6,  0, 0, 1,
            1, 0, 0,  0, 0, 7,  4, 6, 8,
            0, 0, 0,  0, 0, 0,  5, 0, 0,
            
            5, 7, 0,  0, 0, 8,  3, 0, 0,
            0, 9, 3,  6, 0, 5,  0, 1, 7,
            8, 0, 6,  1, 0, 0,  9, 0, 0
        ];
        
        $sudoku = new SudokuValidator($game);
        $sudoku[30] = 3;   // row = 4, column = 4
        $sudoku->echoHtmlTable();
    ?>
</body>
</html>
