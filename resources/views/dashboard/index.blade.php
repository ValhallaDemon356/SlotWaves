<x-app-layout>
    <div class="space-y-8">
        <!-- Greeting Header -->
        <div class="flex justify-between items-center bg-slate-900/40 p-6 rounded-2xl border border-slate-800/80 backdrop-blur-md">
            <div>
                <h1 class="text-2xl font-bold text-slate-100">Airport Control Center</h1>
                <p class="text-sm text-slate-400 mt-1">Hello, {{ auth()->user()->name }} — monitoring base slot coordinates</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold text-slate-300">Operational Date</p>
                <p class="text-lg font-bold text-blue-400 font-mono">{{ now()->format('d M Y') }}</p>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Total Flights -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-blue-500/20">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </div>
                <p class="text-xs tracking-widest text-slate-400 font-mono uppercase font-semibold">Total Flights Managed</p>
                <p class="text-4xl font-bold mt-3 text-slate-100 font-mono">{{ $stats['total_flights'] }}</p>
                <div class="mt-4 flex items-center gap-1 text-emerald-400 text-xs">
                    <span>Active database records</span>
                </div>
            </div>

            <!-- Active Schedules -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-cyan-500/20">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <p class="text-xs tracking-widest text-slate-400 font-mono uppercase font-semibold">Active Schedules</p>
                <p class="text-4xl font-bold mt-3 text-slate-100 font-mono">{{ $stats['active_schedules'] }}</p>
                <div class="mt-4 flex items-center gap-1 text-cyan-400 text-xs">
                    <span>Imported base seasons</span>
                </div>
            </div>

            <!-- Total Uploads -->
            <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-emerald-500/20">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                </div>
                <p class="text-xs tracking-widest text-slate-400 font-mono uppercase font-semibold">Files Uploaded</p>
                <p class="text-4xl font-bold mt-3 text-slate-100 font-mono">{{ $stats['total_uploads'] }}</p>
                <div class="mt-4 flex items-center gap-1 text-emerald-400 text-xs">
                    <span>PDF/Word documents parsed</span>
                </div>
            </div>
        </div>

        <!-- Recent Audit Trail -->
        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6">
            <h2 class="text-lg font-bold text-slate-100 mb-4">Recent Audit Activity</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs text-slate-400 uppercase bg-slate-950/60 border-b border-slate-850">
                        <tr>
                            <th class="py-3 px-4">Operator</th>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">Description</th>
                            <th class="py-3 px-4">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($stats['recent_audits'] as $audit)
                            <tr>
                                <td class="py-3 px-4 font-semibold text-slate-200">
                                    {{ $audit->user ? $audit->user->name : 'System' }}
                                </td>
                                <td class="py-3 px-4 font-mono text-xs uppercase">
                                    <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                        {{ $audit->action }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-mono text-xs text-slate-400">
                                    {{ $audit->auditable_type }} (ID: {{ $audit->auditable_id }})
                                </td>
                                <td class="py-3 px-4">
                                    {{ $audit->description }}
                                </td>
                                <td class="py-3 px-4 text-xs font-mono text-slate-400">
                                    {{ $audit->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-500">
                                    No audit actions recorded yet
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
