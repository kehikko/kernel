<?php

/**
 * Emit a signal that can be cathed elsewhere in the software.
 *
 * When called without any arguments, emits a signal with the same exact
 * arguments as the function/method it was called in. Name of the signal
 * is either the function name or 'class@method'.
 *
 * When called with arguments, first argument is the signal name and the second
 * is an array of arguments given to the signal.
 *
 * @param string $signal Signal name or null
 * @param array  $args   Arguments passed to the signal when $signal is not null
 */
function emit(string $signal = null, array $args = [])
{
    /* automatic signal using caller */
    if ($signal === null) {
        $caller = debug_backtrace(0, 2);
        $signal = (isset($caller[1]['class']) ? $caller[1]['class'] . '@' : '') . $caller[1]['function'];
        $args   = $caller[1]['args'];
    }

    $signals = cfg(['signals', $signal], array());
    if (!is_array($signals)) {
        log_record(LOG_ERR, 'invalid signal configuration: {name}', ['name' => $signal], false);
        return;
    }

    foreach ($signals as $index => $signal) {
        $reflect = tool_call_parse($signal, false);
        if (is_array($reflect)) {
            $reflect['method']->invokeArgs($reflect['object'], $args);
        } else if (is_a($reflect, 'ReflectionFunction')) {
            $reflect->invokeArgs($args);
        } else {
            log_record(LOG_ERR, 'invalid signal configuration: {name}, index: {index}', ['name' => $signal, 'index' => $index], false);
        }
    }
}
