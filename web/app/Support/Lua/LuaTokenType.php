<?php

namespace App\Support\Lua;

enum LuaTokenType
{
    case LBRACE;
    case RBRACE;
    case LBRACKET;
    case RBRACKET;
    case ASSIGN;
    case COMMA;
    case SEMICOLON;
    case STRING;
    case NUMBER;
    case NAME;
    case TRUE;
    case FALSE;
    case NIL;
    case EOF;
}
