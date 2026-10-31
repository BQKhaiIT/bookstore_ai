<?php

namespace Tests\Unit;

use App\Support\CloudinarySearchExpression;
use PHPUnit\Framework\TestCase;

class CloudinarySearchExpressionTest extends TestCase
{
    public function test_builds_safe_search_expression(): void
    {
        $expression = CloudinarySearchExpression::forImageFileNameQuery('Vineland');

        $this->assertStringContainsString('resource_type:image', $expression);
        $this->assertStringContainsString('type:upload', $expression);
        $this->assertStringContainsString('public_id:vineland*', $expression);
        $this->assertStringNotContainsString('*vineland*', $expression);
    }

    public function test_escapes_lucene_special_characters(): void
    {
        $expression = CloudinarySearchExpression::forImageFileNameQuery('bad+query');

        $this->assertStringContainsString('bad\\+query*', $expression);
    }
}
