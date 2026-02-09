<?php

namespace App\Enums;

enum IptCategory: int
{
    case TvPacks = 65;
    case TvX265 = 99;
    case MovieX265 = 100;

    /**
     * Build the query string portion for the given categories.
     *
     * @param  list<self>  $categories
     */
    public static function queryString(array $categories): string
    {
        return implode('&', array_map(
            fn (self $category): string => "{$category->value}=",
            $categories,
        ));
    }
}
