<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Newsletter Archive | UH Population Health</title>
    <meta name="robots" content="index,follow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-uh-red text-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col space-y-2">
                    <h1 class="text-3xl font-bold">Newsletter Archive</h1>
                    <p class="">Browse our collection of past newsletters from UH Population Health</p>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                @if($campaigns->count() > 0)
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($campaigns as $campaign)
                            <article class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 flex flex-col h-full"
                                     aria-labelledby="campaign-{{ $campaign->id }}-title">
                                <div class="p-6 flex-grow">
                                    <div class="flex justify-between items-start mb-2">
                                        <h2 id="campaign-{{ $campaign->id }}-title" class="text-xl font-semibold text-gray-900 line-clamp-2">
                                            {{ $campaign->name }}
                                        </h2>
                                        
                                    </div>
                                    
                                    <div class="mt-1">
                                        <p class="text-sm text-gray-600 mb-2">
                                            <span class="font-medium">Subject:</span> {{ $campaign->subject }}
                                        </p>
                                        @if($campaign->sent_at)
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="flex-shrink-0 mr-1.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                </svg>
                                                {{ $campaign->sent_at->setTimezone(config('app.timezone'))->format('M j, Y') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100">
                                    <a href="{{ route('newsletter.public.campaign.view', $campaign) }}" 
                                       class="inline-flex items-center text-sm font-medium text-red-700 hover:text-red-800"
                                       aria-label="View newsletter {{ $campaign->name }}">
                                        View Newsletter
                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900">No newsletters yet</h3>
                        <p class="mt-1 text-gray-500">Check back later for updates from UH Population Health.</p>
                    </div>
                @endif

                <!-- Pagination -->
                @if($campaigns->hasPages())
                    <div class="mt-12 px-4 sm:px-0">
                        <nav class="flex items-center justify-between" aria-label="Pagination">
                            <div class="hidden sm:block">
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ $campaigns->firstItem() }}</span>
                                    to
                                    <span class="font-medium">{{ $campaigns->lastItem() }}</span>
                                    of
                                    <span class="font-medium">{{ $campaigns->total() }}</span>
                                    results
                                </p>
                            </div>
                            <div class="flex-1 flex items-center justify-between sm:justify-end space-x-2">
                                @if ($campaigns->onFirstPage())
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                                        Previous
                                    </span>
                                @else
                                    <a href="{{ $campaigns->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                @endif

                                <div class="hidden sm:flex space-x-1">
                                    @php
                                        $currentPage = $campaigns->currentPage();
                                        $lastPage = $campaigns->lastPage();
                                        $window = 2; // Number of pages to show before and after current page
                                        
                                        // Always show first page
                                        if ($currentPage > $window + 2) {
                                            echo '<a href="' . $campaigns->url(1) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md ' . ($currentPage == 1 ? 'bg-red-50 text-red-600 border-red-500' : 'bg-white text-gray-700 hover:bg-gray-50') . '">1</a>';
                                            if ($currentPage > $window + 3) {
                                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium text-gray-700">...</span>';
                                            }
                                        }
                                        
                                        // Show pages around current page
                                        for ($i = max(1, $currentPage - $window); $i <= min($lastPage, $currentPage + $window); $i++) {
                                            echo '<a href="' . $campaigns->url($i) . '" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md ' . ($i == $currentPage ? 'bg-red-50 text-red-600 border-red-500' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300') . '">' . $i . '</a>';
                                        }
                                        
                                        // Always show last page
                                        if ($currentPage < $lastPage - $window - 1) {
                                            if ($currentPage < $lastPage - $window - 2) {
                                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium text-gray-700">...</span>';
                                            }
                                            echo '<a href="' . $campaigns->url($lastPage) . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md ' . ($currentPage == $lastPage ? 'bg-red-50 text-red-600 border-red-500' : 'bg-white text-gray-700 hover:bg-gray-50') . '">' . $lastPage . '</a>';
                                        }
                                    @endphp
                                </div>

                                @if ($campaigns->hasMorePages())
                                    <a href="{{ $campaigns->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                @else
                                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                                        Next
                                    </span>
                                @endif
                            </div>
                        </nav>
                    </div>
                @endif
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">
                    &copy; {{ date('Y') }} UH Population Health. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
