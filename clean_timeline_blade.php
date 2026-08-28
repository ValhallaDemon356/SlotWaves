<?php

$filePath = __DIR__ . '/resources/views/timeline.blade.php';
$content = file_get_contents($filePath);

// Target snippet to remove:
$target = '</div>   <div class="ml-auto flex items-center gap-2">
        <a href="{{ route(\'schedule.dashboard\', $upload->id) }}" class="text-[11px] text-slate-400 hover:text-blue-400 transition px-2">â†  Dashboard</a>
        <button @click="downloadJpg" class="text-[11px] bg-blue-600 hover:bg-blue-500 px-3 py-1.5 rounded-lg font-bold text-white transition shadow cursor-pointer flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export JPG
        </button>
        <button @click="printPdf" class="text-[11px] bg-slate-800 hover:bg-slate-700 border border-slate-700 px-3 py-1.5 rounded-lg font-bold text-slate-300 transition cursor-pointer flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print PDF
        </button>
    </div>
</div>';

// Remove with regex or string replace
$newContent = preg_replace('/<\/div>\s*<div class="ml-auto flex items-center gap-2">[\s\S]*?Print PDF\s*<\/button>\s*<\/div>\s*<\/div>/u', '</div>', $content, 1);

if ($newContent !== $content) {
    file_put_contents($filePath, $newContent);
    echo "Cleaned duplicate toolbar successfully.\n";
} else {
    echo "No match found.\n";
}
