<?php



// Example Code
$code = '    
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

    $sudoku = new SudokuSolver($game);
    $sudoku->solve();
    $sudoku->echoHtmlTable();

';


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
 * Sudoku Validator
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




/*
 * Sudoku Solver
 * Solves the Sudoku if it is valid.
 * solve() needs to be called explecitly.
 *
 * For Example:
 * $sudoku = new Sudokusolver($game);  // solve() is call by the constructor.
 * $sudoku[$someIndex] = $value        // Set a field... [optional]
 * $sudoke->solve();                   // solve() the sudoku if possible...
 */
class SudokuSolver extends SudokuValidator {
    
    protected $blankIndices       = []; // Array with blank indices of $game
    protected $startIndices       = []; // Array with start indices of $game
    private        $recusionDeep  = 0;  // How deep objects are cloned.
    private static $instanceCount = 0;  // How many istance are created.
    private        $instance      = 0;  // Instand id
    
    /*
     * Constructor
     */
    public function __construct (array $game,
        int   $blockSizeH = 3,
        int   $blockSizeV = 3) {
            parent::__construct($game, $blockSizeH, $blockSizeV);
            
            self::$instanceCount++;
            $this->instance = self::$instanceCount;
            
            $this->blankIndices = arrayKeysNoVal($this);
            $this->startIndices = array_diff(range(0, $this->size ** 2 - 1),
                $this->blankIndices);
            
            $this->calcPossibleValues();
    }
    
    /*
     * Object cloning...
     */
    public function __clone() {
        parent::__clone();
        
        self::$instanceCount++;
        $this->instance = self::$instanceCount;
        
        $this->recusionDeep++;
        
        $this->calcPossibleValues();
    }
    
    /*
     * Output for debugging...
     * Returns the sudoku as ASCII string, which could be printed
     * in the console.
     * Cloned object are indented.
     */
    public function __toString():string {
        $str = parent::__toString();
        
        $ident = str_repeat("\t", $this->recusionDeep);
        
        $str = $ident . str_replace("\n", "\n$ident", $str);
        return rtrim($str, "\t");
    }
    
    /*
     * Getter
     */
    public function getStartIndices ():array { return $this->startIndices; }
    
    /*
     * Override offestSet
     */
    public function offsetSet ($offset, $value) {
        if (is_int($offset) &&
            $offset >= 0 &&
            $offset < count($this) &&
            (in_array($value, $this->allowedValues) || $value ===0))
        {
            $this->_offsetSet($offset, $value);
        } else {
            trigger_error("Invalid parameter to offsetSet!", E_USER_WARNING);
        }
    }
    
    /*
     * Return TRUE if the sudoku is solved and valid,
     * oherwise FALSE.
     */
    public function status ():bool {
        return !$this->blankIndices && !$this->failedIndices;
    }
    
    /*
     * Resets the sudoku.
     * Delets als values, exept the start values ($startIndices)
     */
    public function reset () {
        $arrSize = count($this->arr);
        for ($i = 0; $i < $arrSize; ++$i) {
            if (!isset($this->startIndices[$i])) {
                $this->arr[$i] = 0;
                $this->blankIndices[$i] = $i;
            }
        }
        $this->calcPossibleValues();
    }
    
    /*
     * Solve the Sudoku.
     */
    public function solve ():bool {
        // Loop until the game is solved.
        while ($this->blankIndices) {
            // Can't solve when there are errors.
            if ($this->failedIndices) { return FALSE; }
            
            switch (TRUE) {
                // Try methode solve1().
                case $this->solve1():
                    break;
                    // If it doesn't work, try solve2().
                case $this->solve2():
                    break;
                    // Try solveBacktragck() (backtrack).
                case $this->solveBacktrack():
                    break;
                    // If nothing works, it can't be solved...
                default:
                    return FALSE;
            }
        }
        return TRUE;
    }
    
    /*
     * Sets a field and deletes the field from $this->blankIndices
     * This methode is called by offsetSet() after $offset and $value are
     * validated.
     * It is also used internaly when $offset and $value needn't to be
     * validated.
     */
    protected function _offsetSet ($offset, $value) {
        $this->arr[$offset] = $value;
        unset ($this->blankIndices[$offset]);
        $this->reducePossibleValues($value, $offset);
    }
    
    /*
     * echo $field
     */
    protected function echoField ($idx, $field) {
        echo '<td class="field';
        if (isset($this->startIndices[$idx])) { echo ' start'; }
        echo '">';
        if ($field && is_scalar($field)) { echo $field; }
        echo '</td>';
    }
    
    /*
     * Calc possible values for each blank field.
     * The possilble values are saved as array in the field.
     */
    private function calcPossibleValues () {
        foreach ($this->blankIndices as $blankIndex) {
            $rowN   = $this->rowN($blankIndex);
            $colN   = $this->colN($blankIndex);
            $blockN = $this->blockN($rowN, $colN);
            
            $possibelValues = array_udiff(
                $this->allowedValues,
                $this->rows->arr[$rowN],
                $this->cols->arr[$colN],
                $this->blocks->arr[$blockN],
                function ($a, $b) {
                    if (is_array($a)) { $a = 0; }
                    if (is_array($b)) { $b = 0; }
                    return (string) $a <=> (string) $b;
                });
            
            $this->arr[$blankIndex] = $possibelValues;
        }
    }
    
    /*
     * Find blank fields with only one possibility and set these fields.
     * Returns the number of fields that could be set.
     */
    private function solve1 ():int {
        $setField = 0;
        foreach ($this->blankIndices as $blankIndex) {
            if (count($this->arr[$blankIndex]) == 1) {
                $this->_offsetSet($blankIndex,
                    reset($this->arr[$blankIndex]));
                $setField++;
            }
        }
        return $setField;
    }
    
    /*
     * Find an empty field where a number can not occur in any other
     * field of the row, column, or block set these fields to that number.
     * Returns the number of fields that could be set.
     */
    private function solve2 ():int {
        $setField = 0;
        foreach ($this->blankIndices as $blankIndex) {
            $rowN = $this->rowN($blankIndex);
            $colN = $this->colN($blankIndex);
            $blockN = $this->blockN($rowN, $colN);
            foreach ($this->arr[$blankIndex] as $val) {
                if ((arrayValCountRecursiv($val,
                    $this->rows->arr[$rowN]) == 1) ||
                    (arrayValCountRecursiv($val,
                        $this->cols->arr[$colN]) == 1) ||
                    (arrayValCountRecursiv($val,
                        $this->blocks->arr[$blockN]) == 1))
                {
                    $this->_offsetSet($blankIndex, $val);
                    $setField++;
                    break;
                }
            }
        }
        return $setField;
    }
    
    /*
     * Use backtrack to solve.
     * Take the smallest blank field and tray all possible Values
     * with new Sudkokus until the puzzle is solved.
     * It is possible that sudokus are generated recursively.
     * Returns true on success, otherwise false.
     */
    private function solveBacktrack ():bool {
        // Search for the smallest blank field.
        $idx = 0;
        $poss = $this->size;
        foreach ($this->arr as $idxA => $field) {
            if (is_array($field)) {
                $possA = count($field);
                if ($possA < $poss) {
                    $poss = $possA;
                    $idx = $idxA;
                    if ($poss == 2) { break; }
                }
            }
        }
        
        foreach ($this->arr[$idx] as $value) {
            
            $sudoku = clone $this;
            $sudoku[$idx] = $value;
            $sudoku->solve();
            
            if ($sudoku->status()) {
                foreach ($sudoku as $idx2 => $value2) {
                    if ($this->arr[$idx2] !== $value2) {
                        $this->_offsetSet($idx2, $value2);
                    }
                }
                
                return TRUE;
            }
        }
        
        return FALSE;
    }
    
    /*
     * Reduce possible values in the relevant rows, cols and blocks
     * of a given game index.
     * $gameIndex    game index
     * $values       The values that should be unset. [array or single value]
     */
    private function reducePossibleValues ($values,
        int $gameIndex) {
            if (!is_array($values)) {
                $values = [$values];
            }
            
            // Walk throught the row, column and block
            $rowN   = $this->rowN($gameIndex);
            $colN   = $this->colN($gameIndex);
            $blockN = $this->blockN($rowN, $colN);
            
            foreach ($this->rows->arr[$rowN] as $idx => &$field) {
                if (is_array($field)) {
                    $field = array_diff($field, $values);
                    if (empty($field)) {
                        $failedIndex = $this->gameIndexFromRow($rowN, $idx);
                        $this->failedIndices[$failedIndex] = $failedIndex;
                    }
                }
            }
            foreach ($this->cols->arr[$colN] as &$field) {
                if (is_array($field)) {
                    $field = array_diff($field, $values);
                    if (empty($field)) {
                        $failedIndex = $this->gameIndexFromCol($colN, $idx);
                        $this->failedIndices[$failedIndex] = $failedIndex;
                    }
                }
            }
            foreach ($this->blocks->arr[$blockN] as &$field) {
                if (is_array($field)) {
                    $field = array_diff($field, $values);
                    if (empty($field)) {
                        $failedIndex = $this->gameIndexFromBlock($blockN, $idx);
                        $this->failedIndices[$failedIndex] = $failedIndex;
                    }
                }
            }
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
        color: #787878;
    }
    
    .start {
        font-weight: bold;
        color: #000000;
    }

</style>
</head>

<body>
    <h1>Sudoku Solver</h1>
    <pre><code><?php echo $code; ?></code></pre>
    <?php eval($code); ?>
</body>
</html>
