<?php

function emit()
{
    /* automatic signal using caller */
    if (func_num_args() < 1) {
        $caller      = debug_backtrace(0, 2);
        $signal_name = (isset($caller[1]['class']) ? $caller[1]['class'] . '@' : '') . $caller[1]['function'];
        $signals     = cfg(['signals', $signal_name], array());
        if (!is_array($signals)) {
            log_record(LOG_ERR, 'invalid signal configuration: {name}', ['name' => $signal_name], false);
            return;
        }
        foreach ($signals as $index => $signal) {
            $reflect = tool_call_parse($signal, false);
            if (is_array($reflect)) {
                $reflect['method']->invokeArgs($reflect['object'], $caller[1]['args']);
            } else if (is_a($reflect, 'ReflectionFunction')) {
                $reflect->invokeArgs($caller[1]['args']);
            } else {
                log_record(LOG_ERR, 'invalid signal configuration: {name}, index: {index}', ['name' => $signal_name, 'index' => $index], false);
            }
        }
    }
}
