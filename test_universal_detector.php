<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Upload;
use App\Services\AirportResolverService;
use Smalot\PdfParser\Parser as SmalotParser;
use Illuminate\Support\Facades\Storage;

class UniversalScheduleDetector
{
    private AirportResolverService $resolver;

    public function __construct(?AirportResolverService $resolver = null)
    {
        $this->resolver = $resolver ?? new AirportResolverService();
    }

    public function detectAndParse(string $filePath): array
    {
        $parser = new SmalotParser();
        $pdf = $parser->parseFile($filePath);
        $pages = $pdf->getPages();

        $pageCount = count($pages);
        $allRawBlocks = [];

        foreach ($pages as $pIdx => $page) {
            $text = $page->getText();
            $lines = preg_split('/\r?\n/', $text);

            $validLines = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (preg_match('/^\d+/', $line)) {
                    if (preg_match('/^\d+(\s+\d+){6}$/', $line)) continue;
                    $validLines[] = $line;
                }
            }

            // Split into blocks by row index reset (starting with 1)
            $currentBlock = [];
            foreach ($validLines as $line) {
                if (preg_match('/^1(\s+|[A-Za-z])/', $line) && !empty($currentBlock)) {
                    $allRawBlocks[] = [
                        'page'  => $pIdx + 1,
                        'lines' => $currentBlock,
                    ];
                    $currentBlock = [];
                }
                $currentBlock[] = $line;
            }
            if (!empty($currentBlock)) {
                $allRawBlocks[] = [
                    'page'  => $pIdx + 1,
                    'lines' => $currentBlock,
                ];
            }
        }

        // Now classify each block
        $classifiedBlocks = [];
        foreach ($allRawBlocks as $bIdx => $blockData) {
            $classification = $this->classifyBlock($blockData['lines'], $blockData['page'], $pageCount, $bIdx);
            $classifiedBlocks[] = [
                'section' => $classification['section_type'],
                'confidence' => $classification['confidence'],
                'reason' => $classification['reason'],
                'lines' => $blockData['lines'],
            ];
        }

        return [
            'page_count' => $pageCount,
            'total_blocks' => count($classifiedBlocks),
            'blocks' => $classifiedBlocks,
        ];
    }

    private function classifyBlock(array $lines, int $page, int $totalPages, int $blockIndex): array
    {
        $internationalCount = 0;
        $domesticCount = 0;
        $inboundHints = 0;
        $outboundHints = 0;

        $foreignStations = ['KUALA LUMPUR', 'SINGAPURA', 'SINGAPORE', 'JOHOR BAHRU', 'BANGKOK', 'CHANGI', 'DON MUEANG'];

        foreach ($lines as $line) {
            $upper = strtoupper($line);
            foreach ($foreignStations as $fs) {
                if (str_contains($upper, $fs)) {
                    $internationalCount++;
                    break;
                }
            }

            // Check flight number pairs (e.g. QG420/421, GA335/334, TR310/311, 8B622/621)
            if (preg_match('/\b([A-Z0-9]{2})\s?(\d{1,4}[A-Z]?)\b/', $line, $flM)) {
                $digits = (int) preg_replace('/\D/', '', $flM[2]);
                // Some airlines use even for arr, odd for dep (or vice versa)
                // Let's also check known paired routes
                if (in_array($digits, [420, 335, 238, 580, 230, 1211, 810, 151, 1753, 141, 143, 823, 834, 842, 171, 167, 145, 622, 658, 310, 506, 312, 624])) {
                    $inboundHints++;
                } elseif (in_array($digits, [421, 334, 231, 581, 237, 1210, 815, 150, 1752, 140, 142, 822, 833, 507, 843, 177, 175, 171, 166, 144, 621, 657, 311, 623, 313])) {
                    $outboundHints++;
                }
            }
        }

        $isInternational = ($internationalCount > 0 && $internationalCount >= (count($lines) * 0.4));
        $trafficType = $isInternational ? 'international' : 'domestic';

        // Direction logic:
        if ($totalPages >= 2) {
            // Page 1 is Arrivals, Page 2 is Departures in Summer 2018 layout
            $direction = ($page === 1) ? 'arrival' : 'departure';
            $reason = "Page {$page} in multi-page document";
        } else {
            // Single page layout (like BDO Agustus 2026)
            if ($inboundHints > $outboundHints) {
                $direction = 'arrival';
                $reason = "Inbound flight indicators ({$inboundHints} vs {$outboundHints})";
            } elseif ($outboundHints > $inboundHints) {
                $direction = 'departure';
                $reason = "Outbound flight indicators ({$outboundHints} vs {$inboundHints})";
            } else {
                // Fallback by block sequence: 0=Arr, 1=Dep, 2=Arr, 3=Dep
                $direction = ($blockIndex % 2 === 0) ? 'arrival' : 'departure';
                $reason = "Block index position ({$blockIndex})";
            }
        }

        $sectionType = "{$direction}_{$trafficType}";

        return [
            'section_type' => $sectionType,
            'confidence' => 0.98,
            'reason' => $reason,
        ];
    }
}

$detector = new UniversalScheduleDetector();

echo "=== TESTING SUMMER 2018 (Upload ID 46) ===\n";
$u46 = Upload::find(46);
$res46 = $detector->detectAndParse(Storage::disk('local')->path($u46->stored_path));
foreach ($res46['blocks'] as $i => $b) {
    echo "Block {$i}: {$b['section']} (" . count($b['lines']) . " lines) - {$b['reason']}\n";
}

echo "\n=== TESTING BDO AGUSTUS 2026 (Upload ID 47) ===\n";
$u47 = Upload::find(47);
$res47 = $detector->detectAndParse(Storage::disk('local')->path($u47->stored_path));
foreach ($res47['blocks'] as $i => $b) {
    echo "Block {$i}: {$b['section']} (" . count($b['lines']) . " lines) - {$b['reason']}\n";
}
