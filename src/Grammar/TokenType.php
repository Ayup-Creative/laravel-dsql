<?php

namespace AyupCreative\AdvancedSearch\Grammar;

enum TokenType: string
{
    case T_LBRACKET = '[';
    case T_RBRACKET = ']';
    case T_LPAREN = '(';
    case T_RPAREN = ')';
    case T_COLON = ':';
    case T_COMMA = ',';
    case T_AND = 'AND';
    case T_OR = 'OR';
    case T_NOT = 'NOT';
    case T_IDENTIFIER = 'IDENTIFIER';
    case T_STRING = 'STRING';
    case T_NUMBER = 'NUMBER';
    case T_SORT = 'sort';
    case T_LIMIT = 'limit';
    case T_SELECT = 'SELECT';
    case T_WHERE = 'WHERE';
    case T_AS = 'AS';
    case T_CAST = 'CAST';
    case T_PLUS = '+';
    case T_MINUS = '-';
    case T_STAR = '*';
    case T_SLASH = '/';
    case T_ARROW = '->';
    case T_LITERAL_TRUE = 'TRUE';
    case T_LITERAL_FALSE = 'FALSE';
    case T_LITERAL_NULL = 'NULL';
    case T_EOF = 'EOF';
}
