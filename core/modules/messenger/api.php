<?php namespace melt\messenger;

/**
 * Displays a message inline in the current request if the
 * application supports displaying of messages.
 * @param string $message Message to display.
 * @param string $status Determines how the message is rendered.
 * Application should at least support good|bad.
 */
function show_message($message, $status = "bad") {
    \melt\View::render("/messenger/message", compact("message", "status"), false, true);
}

/**
 * Pushes message to be displayed on the next render.
 * @param string $message Message to display.
 * @param string $status Determines how the message is rendered.
 * Application should at least support good|bad.
 */
function push_message($message, $status = "bad") {
    $_SESSION['next_flash'] = array($message, $status);
}

/**
 * Redirects and then displays a message.
 * @param string $url Local or full url to redirect too.
 * @param string $message Message to display.
 * @param string $status Determines how the message is rendered.
 * Application should at least support good|bad.
 */
function redirect_message($url, $message, $status = "bad") {
    if (!\melt\string\starts_with($url, "http"))
        $url = url($url);
    push_message($message, $status);
    \melt\request\redirect($url);
}