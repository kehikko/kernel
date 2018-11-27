<?php

function emit()
{
    $caller = debug_backtrace(0, 2);
    $signal_name = (isset($caller[1]['class']) ? $caller[1]['class'] . '@' : '') . $caller[1]['function'];

    $signal = cfg(array('signals', $signal_name));
    
}
