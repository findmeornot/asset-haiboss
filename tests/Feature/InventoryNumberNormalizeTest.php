<?php

namespace Tests\Feature;

use App\Services\InventoryNumberGenerator;
use Tests\TestCase;

/**
 * Unit tests for InventoryNumberGenerator::normalizeManualInput().
 *
 * These tests validate that manual (fallback) kode-inventaris input is correctly
 * normalized to the 7-digit INV-prefixed canonical format, and that exact-match
 * semantics are preserved (no partial matches, no leading-zero collisions).
 */
class InventoryNumberNormalizeTest extends TestCase
{
    public function test_digits_only_normalized_to_7_digits(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('1945'));
    }

    public function test_single_digit_padded_to_7(): void
    {
        $this->assertSame('INV0000007', InventoryNumberGenerator::normalizeManualInput('7'));
    }

    public function test_three_digits_padded_correctly(): void
    {
        $this->assertSame('INV0000400', InventoryNumberGenerator::normalizeManualInput('400'));
    }

    public function test_four_digits_padded_correctly(): void
    {
        $this->assertSame('INV0001400', InventoryNumberGenerator::normalizeManualInput('1400'));
    }

    public function test_seven_digits_unchanged(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('0001945'));
    }

    public function test_400_does_not_equal_1400(): void
    {
        $a = InventoryNumberGenerator::normalizeManualInput('400');
        $b = InventoryNumberGenerator::normalizeManualInput('1400');
        $this->assertSame('INV0000400', $a);
        $this->assertSame('INV0001400', $b);
        $this->assertNotSame($a, $b);
    }

    public function test_inv_prefix_uppercase_stripped_and_padded(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('INV1945'));
    }

    public function test_inv_prefix_lowercase_stripped_and_padded(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('inv1945'));
    }

    public function test_inv_prefix_mixed_case_stripped_and_padded(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('Inv1945'));
    }

    public function test_canonical_form_already_correct_is_returned_as_is(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('INV0001945'));
    }

    public function test_leading_trailing_spaces_trimmed(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('  1945  '));
    }

    public function test_space_around_prefix_trimmed(): void
    {
        $this->assertSame('INV0001945', InventoryNumberGenerator::normalizeManualInput('  INV1945  '));
    }

    public function test_empty_string_returns_null(): void
    {
        $this->assertNull(InventoryNumberGenerator::normalizeManualInput(''));
    }

    public function test_whitespace_only_returns_null(): void
    {
        $this->assertNull(InventoryNumberGenerator::normalizeManualInput('   '));
    }

    public function test_non_numeric_after_prefix_returns_null(): void
    {
        $this->assertNull(InventoryNumberGenerator::normalizeManualInput('INVabc'));
    }

    public function test_random_string_returns_null(): void
    {
        $this->assertNull(InventoryNumberGenerator::normalizeManualInput('laptop'));
    }

    public function test_mixed_alpha_numeric_no_prefix_returns_null(): void
    {
        $this->assertNull(InventoryNumberGenerator::normalizeManualInput('12ab34'));
    }
}