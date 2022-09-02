<?php


function set_phone_byte($phone_name, $bnum)
{
    global $registrars;
    foreach ($registrars as $regis => $reg) {
        foreach ($reg['icoms'] as $icom_name => $icom) {
            foreach ($icom['phones'] as $ph => $ph_val) {
                if ($ph == $phone_name) {
                    $registrars[$regis]['icoms'][$icom_name]['phones'][$ph]['bnum'] = $bnum;
                }
            }
        }
    }
}

function register_byte_num($bnum, $ph_name)
{
    global $parse_tree;
    if(isset($parse_tree['bnum'][$bnum])) {
        Error("Byte No. $bnum already defined at " . $parse_tree['num'][$bnum]);
    }

    $parse_tree['bnum'][$bnum]['line_no'] = get_current_line_no();
    $parse_tree['bunm'][$bnum]['phone_name'] = $ph_name;
    $parse_tree['bnum'][$bnum]['rly_no'] = get_phone_info($ph_name)[1];
    set_phone_byte($ph_name, $bnum);
}

function do_byte()
{
    match_token("byte");
    match_token("(");
    $bnum = get_token();
    match_token(",");
    $phone_name = get_token();
    match_token(")");

    register_byte_num($bnum, $phone_name);
}

?>