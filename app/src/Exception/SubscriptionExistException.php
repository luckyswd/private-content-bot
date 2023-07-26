<?php


namespace App\Exception;


use Exception;

class SubscriptionExistException extends Exception
{
    public function getInfo():string {
        return 'Подписка уже существует';
    }
}