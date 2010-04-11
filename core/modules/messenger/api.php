<?php

namespace nanomvc\messenger;

/**
 * Displays a message inline in the current request if the
 * application supports displaying of messages.
 * @param string $message Message to display.
 * @param string $status Determines how the message is rendered.
 * Application should at least support good|bad.
 */
function showMessage($message, $status = "bad") {
    $controller = new \nanomvc\Controller();
    $controller->message = $message;
    $controller->status = $status;
    \nanomvc\View::render("/messenger/message", $controller, false, true);
}

/**
 * Redirects and then displays a message.
 * @param string $local_url Local url to redirect too.
 * @param string $message Message to display.
 * @param string $status Determines how the message is rendered.
 * Application should at least support good|bad.
 */
function redirectMessage($local_url, $message, $status = "bad") {
    $_SESSION['next_flash'] = array($message, $status);
    api_navigation::redirect(url($url));
}