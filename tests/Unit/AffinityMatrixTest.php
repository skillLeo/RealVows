<?php

namespace Tests\Unit;

use App\Services\Matching\AffinityMatrix;
use App\Services\Sign\SignCalculator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The internal affinity component.
 *
 * This test file names the twelve periods, which is permitted here and in
 * AffinityMatrix alone — neither reaches a browser. The CI content check
 * excludes both paths deliberately.
 */
class AffinityMatrixTest extends TestCase
{
    private AffinityMatrix $matrix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matrix = new AffinityMatrix;
    }

    public function test_the_matrix_is_symmetric(): void
    {
        foreach (AffinityMatrix::ORDER as $a) {
            foreach (AffinityMatrix::ORDER as $b) {
                $this->assertSame(
                    $this->matrix->score($a, $b),
                    $this->matrix->score($b, $a),
                    "Affinity is not symmetric between positions {$a} and {$b}. "
                        .'A pairing must score the same whichever way round it is read.'
                );
            }
        }
    }

    /** Every diagonal cell — a period paired with itself. */
    public function test_every_diagonal_cell_is_the_same_familiar_value(): void
    {
        foreach (AffinityMatrix::ORDER as $key) {
            $this->assertSame(
                75.0,
                $this->matrix->score($key, $key),
                "The diagonal cell for {$key} has drifted."
            );
        }
    }

    /** The four strongest pairings: separation of four, the same element. */
    public function test_the_strongest_pairings(): void
    {
        foreach ([['aries', 'leo'], ['taurus', 'virgo'], ['gemini', 'libra'], ['cancer', 'scorpio']] as [$a, $b]) {
            $this->assertSame(92.0, $this->matrix->score($a, $b), "Expected the strongest value for {$a}/{$b}.");
        }
    }

    /** The four weakest: separation of three, clashing. */
    public function test_the_weakest_pairings(): void
    {
        foreach ([['aries', 'cancer'], ['taurus', 'leo'], ['gemini', 'virgo'], ['cancer', 'libra']] as [$a, $b]) {
            $this->assertSame(40.0, $this->matrix->score($a, $b), "Expected the weakest value for {$a}/{$b}.");
        }
    }

    public function test_every_cell_is_within_the_declared_range(): void
    {
        foreach (AffinityMatrix::ORDER as $a) {
            foreach (AffinityMatrix::ORDER as $b) {
                $score = $this->matrix->score($a, $b);

                $this->assertGreaterThanOrEqual(40.0, $score);
                $this->assertLessThanOrEqual(92.0, $score);
            }
        }
    }

    public function test_the_matrix_covers_all_twelve_positions_with_no_gaps(): void
    {
        $this->assertCount(12, AffinityMatrix::ORDER);
        $this->assertSame(AffinityMatrix::ORDER, array_unique(AffinityMatrix::ORDER));
    }

    /**
     * Missing data returns null, never zero, so the orchestrator redistributes
     * the weight instead of reading the gap as incompatibility.
     */
    public function test_missing_or_unknown_input_returns_null(): void
    {
        $this->assertNull($this->matrix->score(null, 'aries'));
        $this->assertNull($this->matrix->score('aries', null));
        $this->assertNull($this->matrix->score(null, null));
        $this->assertNull($this->matrix->score('aries', 'not-a-real-key'));
    }

    // ── Boundaries ───────────────────────────────────────────────────────
    //
    // Both sides of all twelve transitions. The December/January wrap is the
    // one that breaks naive implementations, so it is covered from four
    // directions: either side of the boundary and either side of the year.

    public static function boundaries(): array
    {
        return [
            'day before aquarius' => ['1990-01-19', 'capricorn'],
            'first day of aquarius' => ['1990-01-20', 'aquarius'],
            'day before pisces' => ['1990-02-18', 'aquarius'],
            'first day of pisces' => ['1990-02-19', 'pisces'],
            'day before aries' => ['1990-03-20', 'pisces'],
            'first day of aries' => ['1990-03-21', 'aries'],
            'day before taurus' => ['1990-04-19', 'aries'],
            'first day of taurus' => ['1990-04-20', 'taurus'],
            'day before gemini' => ['1990-05-20', 'taurus'],
            'first day of gemini' => ['1990-05-21', 'gemini'],
            'day before cancer' => ['1990-06-20', 'gemini'],
            'first day of cancer' => ['1990-06-21', 'cancer'],
            'day before leo' => ['1990-07-22', 'cancer'],
            'first day of leo' => ['1990-07-23', 'leo'],
            'day before virgo' => ['1990-08-22', 'leo'],
            'first day of virgo' => ['1990-08-23', 'virgo'],
            'day before libra' => ['1990-09-22', 'virgo'],
            'first day of libra' => ['1990-09-23', 'libra'],
            'day before scorpio' => ['1990-10-22', 'libra'],
            'first day of scorpio' => ['1990-10-23', 'scorpio'],
            'day before sagittarius' => ['1990-11-21', 'scorpio'],
            'first day of sagittarius' => ['1990-11-22', 'sagittarius'],
            'day before capricorn' => ['1990-12-21', 'sagittarius'],
            'first day of capricorn' => ['1990-12-22', 'capricorn'],

            // The wrap. 31 December and 1 January must land in the same place.
            'new years eve' => ['1990-12-31', 'capricorn'],
            'new years day' => ['1991-01-01', 'capricorn'],

            // Leap day, because February arithmetic is where date bugs live.
            'leap day' => ['1992-02-29', 'pisces'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_boundary_dates_resolve_to_the_right_position(string $date, string $expected): void
    {
        $result = (new SignCalculator)->for(Carbon::parse($date));

        $this->assertSame(
            $expected,
            $result['sign'],
            "{$date} resolved to {$result['sign']} rather than {$expected}."
        );

        // Whatever it resolved to must exist in the matrix, or the score
        // would silently come back null for a real member.
        $this->assertContains($result['sign'], AffinityMatrix::ORDER);
        $this->assertNotNull($this->matrix->score($result['sign'], 'aries'));
    }

    public function test_every_position_the_calculator_can_produce_exists_in_the_matrix(): void
    {
        $calculator = new SignCalculator;
        $produced = [];

        // Walk a full year, day by day. Anything the calculator can return
        // for a real date of birth must be scoreable.
        $day = Carbon::parse('1990-01-01');
        for ($i = 0; $i < 365; $i++) {
            $produced[] = $calculator->for($day)['sign'];
            $day = $day->addDay();
        }

        $produced = array_values(array_unique($produced));

        $this->assertCount(12, $produced, 'A full year should produce all twelve positions.');

        foreach ($produced as $key) {
            $this->assertContains(
                $key,
                AffinityMatrix::ORDER,
                "The calculator can produce [{$key}], which the matrix cannot score."
            );
        }
    }
}
