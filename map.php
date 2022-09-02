<?php

function do_map_action($num, $phone) 
{
    global $parse_tree;
    $phone_info = get_phone_info($phone);
    $regis = $phone_info[6];
    $rly_no = $phone_info[1];

    if(!isset($parse_tree['map'][$regis])) {
        $parse_tree['map'][$regis] = array();
    }

    $parse_tree['map'][$regis][$num] = $rly_no;
    
}

function do_map()
{
    match_token("map");
    match_token("(");
    $num = get_token();
    match_token(",");
    $ph = get_token();
    phone_should_exist($ph);
    match_token(")");

    do_map_action($num, $ph);

}
?>