<?php

class Names {
    private $names = array();

    function exists($n) 
    {
        return isset($this->names[$n]);
    }

    function add_name($n, $lno) 
    {
        if(!$this->exists($n)) {
            $this->names[$n] = $lno;
        } else {
            Error("The name variable $n already defined at " . $names[$n]);
        }
    }

}
        

?>
