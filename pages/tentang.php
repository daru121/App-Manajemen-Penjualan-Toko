<?php
require_once '../backend/check_session.php';
require_once '../backend/database.php';

// Set timezone di awal file
date_default_timezone_set('Asia/Makassar'); // Set timezone ke WITA
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Saya - Jamu Air Mancur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media (max-width: 768px) {
            .mobile-padding {
                padding: 1rem !important;
            }
            .mobile-text {
                font-size: 1.5rem !important;
            }
            .mobile-subtitle {
                font-size: 1rem !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    
    <!-- Main Content Container -->
    <div class="sm:ml-64"> <!-- Sidebar margin -->
        <?php include '../components/navbar.php'; ?>
        
        <!-- Content Area with proper padding -->
        <div class="p-4 sm:p-8 mt-16"> <!-- Added mt-16 for navbar height -->
            <!-- Header Section -->
            <div class="mb-6 bg-gradient-to-br from-indigo-600 via-blue-500 to-blue-400 rounded-2xl sm:rounded-3xl p-4 sm:p-8 text-white shadow-xl sm:shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -translate-y-32 translate-x-32 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-500/20 rounded-full translate-y-32 -translate-x-32 blur-3xl"></div>

                <div class="relative">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">Tentang Saya</h1>
                    <p class="text-sm sm:text-lg text-blue-100">Informasi pribadi</p>
                </div>
            </div>

            <!-- Profile Content - Responsive grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
                <!-- Profile Card -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6">
                        <div class="flex flex-col items-center text-center">
                            <!-- Profile Image - Responsive size -->
                            <div class="w-24 sm:w-32 h-24 sm:h-32 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 p-1.5 mb-4 shadow-xl hover:scale-105 transition-transform duration-300">
                                <img src="../img/profile.jpg" 
                                     alt="Daru Caraka" 
                                     class="w-full h-full rounded-full object-cover border-2 border-white"
                                     style="aspect-ratio: 1/1;"
                                >
                            </div>
                            <h2 class="text-lg sm:text-xl font-bold text-gray-900 mb-1">Daru Caraka</h2>
                            <p class="text-sm sm:text-base text-gray-500 mb-4">Data Scientist & ML Engineer</p>

                            <!-- Status Badge -->
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                Yare yare daze
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main Content - Responsive spacing -->
                <div class="lg:col-span-2 space-y-4 sm:space-y-6">
                    <!-- About Section -->
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Tentang Saya</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">
                            Hai! Saya Daru Caraka, seorang Data Scientist dan Machine Learning Engineer yang juga memiliki minat dalam pengembangan web.
                            Saya fokus dalam mengembangkan solusi AI/ML dan aplikasi berbasis data untuk memecahkan masalah bisnis yang kompleks.
                            Di luar pekerjaan dengan data dan model, saya aktif mengeksplorasi teknologi AI terbaru,
                            berkontribusi dalam proyek data science, dan sesekali mengembangkan aplikasi web untuk
                            visualisasi data interaktif.
                        </p>
                    </div>

                    <!-- Areas of Interest - Responsive grid -->
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Area Minat</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div class="flex items-start gap-3">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Machine Learning & AI</h4>
                                    <p class="text-sm text-gray-500">Pengembangan model prediktif dan sistem AI</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="p-2 bg-green-100 rounded-lg">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Data Analysis</h4>
                                    <p class="text-sm text-gray-500">Analisis data dan visualisasi interaktif</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="p-2 bg-purple-100 rounded-lg">
                                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Big Data</h4>
                                    <p class="text-sm text-gray-500">Pengolahan dan analisis data skala besar</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="p-2 bg-pink-100 rounded-lg">
                                    <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Web Development</h4>
                                    <p class="text-sm text-gray-500">Pengembangan aplikasi web interaktif</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tools & Technologies - Responsive text -->
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-6">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Tools & Technologies</h3>
                        <div class="space-y-3 sm:space-y-4">
                            <!-- Technology items with responsive layout -->
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                <span class="text-xs sm:text-sm font-medium text-gray-700">Programming Language</span>
                                <span class="text-xs sm:text-sm text-gray-500">Python • R • Julia • JavaScript • PHP</span>
                            </div>
                            
                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Machine Learning & AI</span>
                                    <span class="text-xs sm:text-sm text-gray-500">TensorFlow • PyTorch • Scikit-learn</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Data Processing</span>
                                    <span class="text-xs sm:text-sm text-gray-500">NumPy • Pandas • SciPy • R tidyverse</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Data Visualization</span>
                                    <span class="text-xs sm:text-sm text-gray-500">Tableau • Power BI • Matplotlib • Seaborn • Plotly</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Big Data & Cloud</span>
                                    <span class="text-xs sm:text-sm text-gray-500">Apache Spark • Hadoop • AWS • Google Cloud</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Database Management</span>
                                    <span class="text-xs sm:text-sm text-gray-500">MySQL • PostgreSQL • MongoDB</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Development Tools</span>
                                    <span class="text-xs sm:text-sm text-gray-500">Git • Docker • Jupyter • VS Code • RStudio</span>
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 sm:gap-0">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Web Development</span>
                                    <span class="text-xs sm:text-sm text-gray-500">HTML • CSS • React • Node.js • Laravel • Next.js • Tailwind CSS • Bootstrap</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Skills Section -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Skills</h3>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg text-sm font-medium">Python</span>
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-sm font-medium">Machine Learning</span>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm font-medium">Analisis Data</span>
                            <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm font-medium">Deep Learning</span>
                            <span class="px-3 py-1 bg-pink-100 text-pink-700 rounded-lg text-sm font-medium">SQL</span>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded-lg text-sm font-medium">Pengembangan Web</span>
                        </div>
                    </div>

                    <!-- Education Background -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Pendidikan</h3>
                        <div class="space-y-4">
                            <div class="flex gap-4">
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0L3 9m9 5l9-5" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">STMIK Widya Cipta Dharma Samarinda</h4>
                                    <p class="text-sm text-gray-500">Sistem Informasi</p>
                                    <p class="text-sm text-gray-400">2022 - Sekarang</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Links - Responsive grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <!-- Instagram -->
                        <a href="https://instagram.com/daru_caraka" target="_blank"
                            class="group bg-gradient-to-br from-pink-500 via-red-500 to-yellow-500 p-3 sm:p-4 rounded-xl text-white shadow-sm hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center gap-3 sm:gap-4">
                                <div class="p-2 bg-white/10 rounded-lg">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                                    </svg>
                                </div>
                                <span class="text-sm sm:text-base font-medium">@daru_caraka</span>
                            </div>
                        </a>

                        <!-- WhatsApp -->
                        <a href="https://wa.me/6285247694758" target="_blank"
                            class="group bg-gradient-to-br from-green-500 to-emerald-400 p-3 sm:p-4 rounded-xl text-white shadow-sm hover:shadow-lg transition-all duration-300">
                            <div class="flex items-center gap-3 sm:gap-4">
                                <div class="p-2 bg-white/10 rounded-lg">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                    </svg>
                                </div>
                                <span class="text-sm sm:text-base font-medium">+62 852-4769-4758</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>