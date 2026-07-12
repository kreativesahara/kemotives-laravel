<?php

namespace App\Enums;

enum Role: int
{
    case Visitor = 1;
    case Seller = 3;
    case Moderator = 4;
    case Admin = 5;
}
