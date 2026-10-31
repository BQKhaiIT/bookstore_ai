<?php

namespace App\Support;

final class CloudinarySearchExpression
{
    /**
     * Build a Cloudinary Search API expression (prefix / token match on public_id, filename, display_name).
     */
    public static function forImageFileNameQuery(string $term): string
    {
        $term = trim($term);
        if ($term === '') {
            return 'resource_type:image AND type:upload';
        }

        $tokens = preg_split('/[\s\/\\\\\-_]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokenClauses = [];

        foreach ($tokens as $token) {
            $escaped = self::escapeLuceneTerm($token);
            if ($escaped === '') {
                continue;
            }
            $tokenClauses[] = '(public_id:'.$escaped.'* OR filename:'.$escaped.'* OR display_name:'.$escaped.'*)';
        }

        if ($tokenClauses === []) {
            return 'resource_type:image AND type:upload';
        }

        $match = count($tokenClauses) === 1
            ? $tokenClauses[0]
            : '('.implode(' AND ', $tokenClauses).')';

        return 'resource_type:image AND type:upload AND '.$match;
    }

    private static function escapeLuceneTerm(string $term): string
    {
        $term = mb_strtolower($term, 'UTF-8');

        return preg_replace('/([+\-&|!(){}\[\]^"~*?:\\\\\/])/u', '\\\\$1', $term) ?? '';
    }
}
