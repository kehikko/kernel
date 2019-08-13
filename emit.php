<?php

/**
 * Emit a signal that can be caught elsewhere in the software.
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
        if (empty($args)) {
            $args = $caller[1]['args'];
        }
    }

    $signals = cfg(['signals', $signal], array());
    if (!is_array($signals)) {
        log_record(LOG_ERR, 'Invalid signal configuration: {name}', ['name' => $signal], false);
        return;
    }

    foreach ($signals as $index => $signal) {
        /* create and use call manually here instead of tool_call() or tool_call_simple()
         * because emit must not use current context arguments
         */
        $reflect = tool_call_parse($signal, $args, false);
        if (isset($reflect['method'])) {
            $reflect['method']->invokeArgs($reflect['object'], $reflect['args']);
        } else if (isset($reflect['object'])) {
            $reflect['object']->newInstanceArgs($reflect['args']);
        } else if (isset($reflect['function'])) {
            $reflect['function']->invokeArgs($reflect['args']);
        } else {
            log_record(LOG_ERR, 'Invalid signal configuration: {name}, index: {index}', ['name' => $signal, 'index' => $index], false);
        }
    }
}
