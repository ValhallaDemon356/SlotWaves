<?php

namespace Tests\Unit;

use App\Services\DosFilterService;
use Tests\TestCase;

class DosFilterServiceTest extends TestCase
{
    private DosFilterService $dosService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dosService = new DosFilterService();
    }

    /**
     * TEST 1: DOS normalization
     */
    public function test_dos_normalization(): void
    {
        $this->assertEquals("1357", $this->dosService->normalizeDos("1, 3, 5, 7"));
        $this->assertEquals("1357", $this->dosService->normalizeDos("7,5,3,1"));
        $this->assertEquals("246", $this->dosService->normalizeDos("2, 4, 6"));
        $this->assertEquals("246", $this->dosService->normalizeDos("6-4-2"));
        $this->assertEquals("1234567", $this->dosService->normalizeDos("1 2 3 4 5 6 7"));
        $this->assertEquals("1234567", $this->dosService->normalizeDos("daily"));
        $this->assertEquals("", $this->dosService->normalizeDos("all"));
    }

    /**
     * TEST 2: Single day containment matching (DOS 1 / DOS 2)
     */
    public function test_single_day_containment_matching(): void
    {
        // DOS 1
        $this->assertTrue($this->dosService->matches("1", "1"));
        $this->assertTrue($this->dosService->matches("1357", "1"));
        $this->assertTrue($this->dosService->matches("1234567", "1"));
        $this->assertTrue($this->dosService->matches("12", "1"));
        $this->assertFalse($this->dosService->matches("246", "1"));
        $this->assertFalse($this->dosService->matches("357", "1"));

        // DOS 2
        $this->assertTrue($this->dosService->matches("2", "2"));
        $this->assertTrue($this->dosService->matches("246", "2"));
        $this->assertTrue($this->dosService->matches("1234567", "2"));
        $this->assertTrue($this->dosService->matches("12", "2"));
        $this->assertFalse($this->dosService->matches("1357", "2"));
        $this->assertFalse($this->dosService->matches("1", "2"));
    }

    /**
     * TEST 3: Multi-day exact pattern matching (DOS 2,4,6 / DOS 1,3,5,7)
     */
    public function test_multi_day_exact_pattern_matching(): void
    {
        // DOS 2,4,6
        $this->assertTrue($this->dosService->matches("246", "246"));
        $this->assertTrue($this->dosService->matches("2,4,6", "6,2,4"));
        $this->assertFalse($this->dosService->matches("1234567", "246"));
        $this->assertFalse($this->dosService->matches("2", "246"));
        $this->assertFalse($this->dosService->matches("24", "246"));
        $this->assertFalse($this->dosService->matches("1246", "246"));

        // DOS 1,3,5,7
        $this->assertTrue($this->dosService->matches("1357", "1357"));
        $this->assertFalse($this->dosService->matches("1234567", "1357"));
        $this->assertFalse($this->dosService->matches("1", "1357"));
        $this->assertFalse($this->dosService->matches("135", "1357"));
        $this->assertFalse($this->dosService->matches("246", "1357"));
    }

    /**
     * TEST 4: Daily exact matching ("1234567")
     */
    public function test_daily_exact_matching(): void
    {
        $this->assertTrue($this->dosService->matches("1234567", "1234567"));
        $this->assertTrue($this->dosService->matches("1234567", "daily"));
        $this->assertFalse($this->dosService->matches("1357", "1234567"));
        $this->assertFalse($this->dosService->matches("246", "daily"));
        $this->assertFalse($this->dosService->matches("12345", "1234567"));
    }
}
