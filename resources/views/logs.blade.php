<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logs — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .log-detail { display: none; }
        .log-detail.open { display: block; }
        .log-row { cursor: pointer; }
        .log-row:hover { opacity: 0.85; }
        .chevron { transition: transform 0.2s; }
        .chevron.open { transform: rotate(90deg); }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen">

    {{-- Header --}}
    <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-white">📋 Log Viewer</h1>
            <p class="text-gray-400 text-sm mt-1">{{ config('app.name') }} — Laravel v{{ app()->version() }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-gray-500 text-xs" id="autoRefreshLabel">Auto-refresh: OFF</span>
            <button onclick="toggleAutoRefresh()" id="autoRefreshBtn"
                class="px-3 py-1.5 text-xs rounded bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300">
                ⏱ Auto-refresh
            </button>
            <a href="/" class="px-3 py-1.5 text-xs rounded bg-blue-600 hover:bg-blue-700 text-white">
                🔄 Rafraîchir
            </a>
            <form method="POST" action="/logs/clear" class="inline" onsubmit="return confirm('Vider tous les logs ?')">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-xs rounded bg-red-600 hover:bg-red-700 text-white">
                    🗑 Vider les logs
                </button>
            </form>
        </div>
    </header>

    @if(session('success'))
        <div class="bg-green-900/50 border border-green-700 text-green-300 px-6 py-3 text-sm">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="bg-gray-900 border-b border-gray-800 px-6">
        <nav class="flex gap-1 overflow-x-auto py-2" id="tabs">
            <button onclick="filterLogs('all')" data-tab="all"
                class="tab-btn active px-3 py-1.5 text-xs rounded-full font-medium bg-blue-600 text-white">
                All <span class="ml-1 opacity-75">({{ $counts['all'] }})</span>
            </button>
            @foreach($levels as $level)
                @php
                    $colors = [
                        'emergency' => 'bg-red-900 text-red-200',
                        'alert'     => 'bg-red-800 text-red-200',
                        'critical'  => 'bg-red-700 text-red-100',
                        'error'     => 'bg-orange-800 text-orange-200',
                        'warning'   => 'bg-yellow-800 text-yellow-200',
                        'notice'    => 'bg-cyan-800 text-cyan-200',
                        'info'      => 'bg-blue-800 text-blue-200',
                        'debug'     => 'bg-gray-700 text-gray-300',
                    ];
                    $badgeColors = [
                        'emergency' => 'bg-red-500',
                        'alert'     => 'bg-red-400',
                        'critical'  => 'bg-red-600',
                        'error'     => 'bg-orange-500',
                        'warning'   => 'bg-yellow-500',
                        'notice'    => 'bg-cyan-500',
                        'info'      => 'bg-blue-500',
                        'debug'     => 'bg-gray-500',
                    ];
                @endphp
                <button onclick="filterLogs('{{ $level }}')" data-tab="{{ $level }}"
                    class="tab-btn px-3 py-1.5 text-xs rounded-full font-medium bg-gray-800 text-gray-400 hover:bg-gray-700">
                    {{ ucfirst($level) }}
                    @if($counts[$level] > 0)
                        <span class="ml-1 inline-flex items-center justify-center w-5 h-5 text-[10px] rounded-full {{ $badgeColors[$level] }} text-white">
                            {{ $counts[$level] }}
                        </span>
                    @endif
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Logs list --}}
    <main class="px-6 py-4 space-y-1">
        @forelse($logs as $index => $log)
            @php
                $levelBg = [
                    'emergency' => 'border-red-500 bg-red-950/50',
                    'alert'     => 'border-red-400 bg-red-950/30',
                    'critical'  => 'border-red-600 bg-red-950/40',
                    'error'     => 'border-orange-500 bg-orange-950/30',
                    'warning'   => 'border-yellow-500 bg-yellow-950/20',
                    'notice'    => 'border-cyan-500 bg-cyan-950/20',
                    'info'      => 'border-blue-500 bg-blue-950/20',
                    'debug'     => 'border-gray-600 bg-gray-900/50',
                ];
                $levelText = [
                    'emergency' => 'text-red-400',
                    'alert'     => 'text-red-300',
                    'critical'  => 'text-red-400',
                    'error'     => 'text-orange-400',
                    'warning'   => 'text-yellow-400',
                    'notice'    => 'text-cyan-400',
                    'info'      => 'text-blue-400',
                    'debug'     => 'text-gray-400',
                ];
                // Split message: first line = summary, rest = details
                $lines = explode("\n", $log['message']);
                $summary = $lines[0];
                $details = count($lines) > 1 ? implode("\n", array_slice($lines, 1)) : null;
            @endphp

            <div class="log-entry border-l-4 rounded-r {{ $levelBg[$log['level']] ?? 'border-gray-600 bg-gray-900' }}"
                 data-level="{{ $log['level'] }}">

                {{-- Accordion header --}}
                <div class="log-row flex items-start gap-3 px-4 py-3" onclick="toggleLog({{ $index }})">
                    <span class="chevron text-gray-500 mt-0.5 text-xs" id="chevron-{{ $index }}">▶</span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] font-mono text-gray-500">{{ $log['date'] }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider {{ $levelText[$log['level']] ?? 'text-gray-400' }}">
                                {{ $log['level'] }}
                            </span>
                            <span class="text-[10px] text-gray-600">{{ $log['env'] }}</span>
                        </div>
                        <p class="text-sm text-gray-200 truncate font-mono">{{ Str::limit($summary, 200) }}</p>
                    </div>
                </div>

                {{-- Accordion detail --}}
                <div class="log-detail px-4 pb-4 pl-10" id="detail-{{ $index }}">
                    <pre class="text-xs text-gray-400 font-mono whitespace-pre-wrap break-all bg-black/30 rounded p-3 max-h-96 overflow-auto">{{ $log['message'] }}</pre>
                </div>
            </div>
        @empty
            <div class="text-center py-20 text-gray-500">
                <p class="text-4xl mb-4">📭</p>
                <p class="text-lg">Aucun log trouvé</p>
                <p class="text-sm mt-2">Les logs apparaîtront ici quand l'application génèrera des entrées.</p>
            </div>
        @endforelse
    </main>

    <script>
        function toggleLog(index) {
            const detail = document.getElementById('detail-' + index);
            const chevron = document.getElementById('chevron-' + index);
            detail.classList.toggle('open');
            chevron.classList.toggle('open');
        }

        function filterLogs(level) {
            // Update tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white');
                btn.classList.add('bg-gray-800', 'text-gray-400');
            });
            const activeBtn = document.querySelector(`[data-tab="${level}"]`);
            activeBtn.classList.remove('bg-gray-800', 'text-gray-400');
            activeBtn.classList.add('bg-blue-600', 'text-white');

            // Filter entries
            document.querySelectorAll('.log-entry').forEach(entry => {
                if (level === 'all' || entry.dataset.level === level) {
                    entry.style.display = '';
                } else {
                    entry.style.display = 'none';
                }
            });
        }

        // Auto-refresh
        let autoRefreshInterval = null;
        function toggleAutoRefresh() {
            const label = document.getElementById('autoRefreshLabel');
            const btn = document.getElementById('autoRefreshBtn');
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                label.textContent = 'Auto-refresh: OFF';
                btn.classList.remove('bg-green-700');
                btn.classList.add('bg-gray-800');
            } else {
                autoRefreshInterval = setInterval(() => location.reload(), 5000);
                label.textContent = 'Auto-refresh: 5s';
                btn.classList.remove('bg-gray-800');
                btn.classList.add('bg-green-700');
            }
        }
    </script>
</body>
</html>
