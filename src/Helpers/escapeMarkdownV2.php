<?php
function escapeMarkdownV2(string $text): string {
    $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    $escaped = '';
    $len = mb_strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($text, $i, 1);
        if (in_array($char, $specialChars, true)) {
            $escaped .= '\\' . $char;
        } else {
            $escaped .= $char;
        }
    }
    return $escaped;
}