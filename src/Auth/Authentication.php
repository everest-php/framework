<?php


namespace everest\Auth;


use everest\Storage\Session;

class Authentication
{

    public static function user($parameter){
        return Session::getItem("xoauth.".$parameter);
    }

}