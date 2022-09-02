<?php

$routes = array();
static $g_pattern = array();


function do_route($l)
{
    global $routes, $names;

    match_token("route");
    $rname = get_token();
    if (isset($routes[$rname])) {
        Error("This route name already exists.");
    }
    $routes[$rname] = array();

    match_token("(");
    $name = get_token();
    if (!$names->exists($name)) {
        Error("Variable name $name is not defined!");
    }

    $routes[$rname]['name'] = $name;
    match_token(",");
    $pat = get_token_till(",");
    $routes[$rname]['pat'] = $pat;
    match_token(",");
    $routes[$rname]['route_specs'] = array();
    do_route_specs($rname);
    //match_token(")");

    $routes[$rname]['route_specs'] = array_reverse($routes[$rname]['route_specs']);

    global $debug;
    if ($debug) {
        print_r($routes);
    }
}

function do_route_fn() 
{
    $rv = array();
    $fn = get_token();
    $rv['fn'] = $fn;
    switch ($fn) {
    case "prefix":
    case "postfix":
        match_token("(");
        $num = get_token();
        //validate_digits($num);
        $rv['val'] = $num;
        match_token(")");
        break;
    case "slice":
        match_token("(");
        $offset = get_token();
        //validate_digits($offset);
        $val = $offset;
        if (lookup() == ':') {
            /* length also provided in slice */
            match_token(":");
            $len = get_token();
            //validate_digits($len);
            $val .= ":$len";
        } 
        $rv['val'] = $val;
        match_token(")");
        break;
    case "preslice":
        match_token("(");
        $pre = get_token();
        match_token(",");
        $offset = get_token();
        if (lookup() == ':') {
            match_token(":");
            $len = get_token();
            $offset .= ":$len";
        }
        match_token(")");
        $rv['val'] = array($pre, $offset);
        break;
    default:
        Error("Unknown route translation function \"$fn\".");
    }
    
    return $rv;
}



function do_route_fn_(& $arr) 
{

    $rv = array();
    $fn = get_token();
    $rv['fn'] = $fn;
    switch ($fn) {
    case "prefix":
    case "postfix":
        match_token("(");
        $num = get_token();
        //validate_digits($num);
        $rv['val'] = $num;
        match_token(")");
        array_push($arr, $rv);
        if (lookup() == '!') {
            match_token("!");
            do_route_fn($arr);
        }
        break;
    case "slice":
        match_token("(");
        $offset = get_token();
        //validate_digits($offset);
        $val = $offset;
        if (lookup() == ':') {
            /* length also provided in slice */
            match_token(":");
            $len = get_token();
            //validate_digits($len);
            $val .= ":$len";
        } 
        $rv['val'] = $val;
        match_token(")");
        array_push($arr, $rv);
        if (lookup() == '!') {
            match_token("!");
            do_route_fn($arr);
        }
        break;
    default:
        Error("**Unknown route translation function \"$fn\".");
    }
    //return $arr;
}


function do_route_specs($rname) 
{
    DEBUG("In do_route_specs()");
    global $routes, $names;
    
    $rv = array();

    $tech = get_token();
    switch($tech) {
    case "pri":
    case "sip":
        $rv['tech'] = $tech;
        match_token(":");
        $n = get_token();
        if(!$names->exists($n) and $tech != "pri") {
            Error("The varable name $n is not defined!");
        }
        $rv['name'] = $n;
        $chr = lookup();
        switch($chr) {
        case ':':
            match_token(":");
            $rv['fn'] = do_route_fn();
            if (lookup() == '|') {
                match_token('|');
                do_route_specs($rname);
            } else {
                match_token(")");
            }
            break;
        case '|':
            match_token('|');
            do_route_specs($rname);
            break;
        case ')':
            match_token(")");
            break;
        default:
            Error("Unknown token $chr.");
            break;
        }
            
        break;
    default: 
         Error("Unknown technology $tech.");
        break;
    }
    if(!isset($routes[$rname]['route_specs'])) {
        $routes[$rname]['route_specs'] = array();
    }
    array_push($routes[$rname]['route_specs'], $rv);
    global $debug;
    if ($debug) {
        print_r($rv);
    }
    DEBUG("Out of do_route_specs()");
}

?>