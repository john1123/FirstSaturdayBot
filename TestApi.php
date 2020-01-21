<?php

namespace Telegram\Bot;

use John1123\Logger\File as Logger;

class Api
{
    protected $logger;

    public function __construct($token)
    {
        $this->logger = new Logger('./data/testApi_' . date('Ymd') . '.log');
        $this->logger->log('new Api(' . $token . ')');
    }
    public function getWebhookUpdates($text='')
    {
        $this->logger->log('getWebhookUpdates(' . $text . ')');
        return strlen($text) == 0 ? [] : [
            "update_id" => 380366875,
            "message" => [
                "message_id" => 152,
                "from" => [
                    "id" => 127105606,
                    "is_bot" => false,
                    "first_name" => "Test User",
                    "username" => "testNickname"
                ],
                "chat" => [
                    "id" => 127105606,
                    "first_name" => "Test User",
                    "username" => "testNickname",
                    "type" => "private"
                ],
                "date" => 1570269947,
                "text" => $text,
                "entities" => [
                    "offset" => 0,
                    "length" => 6,
                    "type" => "bot_command"
                ]
            ]
        ];
    }
    public function replyKeyboardMarkup(array $params)
    {
        //$this->logger->log('replyKeyboardMarkup(' . json_encode($params, JSON_UNESCAPED_UNICODE) . ')');
        return json_encode($params, JSON_UNESCAPED_UNICODE);

    }
    public function sendMessage(array $params)
    {
        $this->logger->log('sendMessage(' . json_encode($params, JSON_UNESCAPED_UNICODE) . ')');
        return json_encode($params, JSON_UNESCAPED_UNICODE);

    }
    public function sendDocument(array $params)
    {
        $this->logger->log('sendDocument(' . json_encode($params, JSON_UNESCAPED_UNICODE) . ')');
        return json_encode($params, JSON_UNESCAPED_UNICODE);

    }
}