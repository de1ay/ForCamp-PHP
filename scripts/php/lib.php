<?php

/*
Коды:
200 - OK
400 - Ошибка при создании сессии
401 - Ошибка при генерации id сессии
402 - Ошибка при сохранении сессии
500 - Ошибка при создании подключения к базе данных
501 - Ошибка при выполнении запроса к базе данных
502 - Ошибка при закрытии соединения с базой данных
503 - Ошибка при смене базы данных
600 - Неверный формат входных данных
601 - Неверный логин/пароль
602 - Неверный токен
603 - Пользователя с таким логином не существует
*/

/**
 * @param array $Array
 */
function EchoJSON($Array)
{
    try {
        echo json_encode($Array);
    } catch (Exception $e) {
        exit(json_encode(["status" => "ERROR", "code" => 600]));
    }
}

/**
 * @param string $str
 * @return string
 */
function EncodeAES($str)
{
    $SecretKey = "bb62afd41e03935f138221d05aeca1a4847c0c154fad323b3a09c8128054a4d7";
    $Salt = "f59761522aaf0cf9";
    $str = base64_encode($str);
    $EncStr = openssl_encrypt($str, ENCRYPT_METHOD, $SecretKey, OPENSSL_ZERO_PADDING, $Salt);
    return $EncStr;
}

/**
 * @param string $str
 * @return string
 */
function DecodeAES($str)
{
    $SecretKey = "bb62afd41e03935f138221d05aeca1a4847c0c154fad323b3a09c8128054a4d7";
    $Salt = "f59761522aaf0cf9";
    $str = openssl_decrypt($str, ENCRYPT_METHOD, $SecretKey, OPENSSL_ZERO_PADDING, $Salt);
    $str = base64_decode($str);
    return $str;
}
