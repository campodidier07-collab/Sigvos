<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Desarrollador</title>
    <link href="../../public/img/icono-pagina.png" rel="icon" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        agro: {
                            50: '#f2fbf5',
                            100: '#e1f6e8',
                            200: '#c4ebd4',
                            300: '#96d9b4',
                            400: '#5fc18f',
                            500: '#3aa574',
                            600: '#2b845a',
                            700: '#25694a',
                            800: '#21543d',
                            900: '#1d4533',
                            950: '#0f271d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f2fbf5; color: #1d4533; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(58, 165, 116, 0.15); box-shadow: 0 8px 30px rgba(29, 69, 51, 0.04); }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Navegación -->
    <nav class="bg-gradient-to-r from-agro-900 to-agro-700 border-b border-white/10 shadow-lg">
        <div class="max-w-7xl mx-auto px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-xl border border-white/20 flex items-center justify-center shadow-lg">
                    <img src="../../public/img/icono.png" alt="Logo SIGVOS" class="w-8 h-8 object-contain">
                </div>
                <span class="text-3xl font-heading font-extrabold tracking-tight drop-shadow-md text-white">
                    SIG<span class="text-agro-400">VOS</span>
                </span>
            </div>
            <a href="../../public/index.php" class="flex items-center text-sm font-semibold text-agro-100 hover:text-white transition group bg-white/5 px-4 py-2 rounded-xl border border-white/10 hover:bg-white/10">
                <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>
                Volver al inicio
            </a>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="flex-1 py-16 px-4">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-heading font-extrabold text-agro-900 drop-shadow-sm">Desarrollador</h2>
            <div class="w-20 h-1.5 bg-agro-400 mx-auto mt-4 rounded-full shadow-sm"></div>
            <p class="text-agro-700/70 mt-4 text-lg font-medium">Conoce al desarrollador de este sistema</p>
        </div>

        <div class="flex justify-center">
            <div class="glass-card rounded-3xl p-14 flex flex-col items-center w-full max-w-lg transition-transform hover:-translate-y-1 hover:shadow-[0_20px_50px_rgba(33,84,61,0.1)]">
                
                <div class="w-48 h-48 rounded-full border-4 border-agro-400 p-1 overflow-hidden mb-6 shadow-xl bg-white relative group">
                    <img src="../../img/dev.jpeg" alt="Didier Arley Puentes Campo" class="w-full h-full object-cover object-top rounded-full transition duration-500 group-hover:scale-110">
                </div>
                
                <span class="bg-agro-50 text-agro-600 border border-agro-200 text-xs font-bold uppercase tracking-wider px-4 py-1.5 rounded-full mb-5 shadow-sm">
                    Aprendiz SENA
                </span>
                
                <h3 class="text-3xl font-heading font-bold text-agro-900 text-center leading-snug mb-6">Didier Arley<br>Puentes Campo</h3>
                
                <div class="flex flex-wrap justify-center gap-2 mt-2 mb-10">
                    <span class="bg-white text-agro-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-agro-100 shadow-sm">Frontend & Backend</span>
                    <span class="bg-white text-agro-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-agro-100 shadow-sm">Diseño UI/UX</span>
                    <span class="bg-white text-agro-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-agro-100 shadow-sm">Análisis de Requisitos</span>
                    <span class="bg-white text-agro-700 text-xs font-semibold px-3 py-1.5 rounded-full border border-agro-100 shadow-sm">Tester</span>
                </div>
                
                <div class="flex space-x-5">
                    <a href="https://wa.me/573156320923" target="_blank"
                        class="w-12 h-12 rounded-full flex items-center justify-center text-white text-xl transition-all hover:-translate-y-1 hover:shadow-lg"
                        style="background-color: #25D366; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.3);">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="https://www.facebook.com/didier.campo" target="_blank"
                        class="w-12 h-12 rounded-full flex items-center justify-center text-white text-xl transition-all hover:-translate-y-1 hover:shadow-lg"
                        style="background-color: #1877F2; box-shadow: 0 4px 15px rgba(24, 119, 242, 0.3);">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/didierpuentes2" target="_blank"
                        class="w-12 h-12 rounded-full flex items-center justify-center text-white text-xl transition-all hover:-translate-y-1 hover:shadow-lg"
                        style="background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fd5949 45%, #d6249f 60%, #285AEB 90%); box-shadow: 0 4px 15px rgba(214, 36, 159, 0.3);">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Sección Créditos -->
    <section class="py-24 px-8 bg-white border-t border-agro-100 relative overflow-hidden">
        <div class="absolute inset-0 bg-pattern opacity-5"></div>
        <div class="max-w-7xl mx-auto relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-heading font-extrabold text-agro-900 drop-shadow-sm">Créditos</h2>
                <div class="w-20 h-1.5 bg-agro-400 mx-auto mt-4 rounded-full shadow-sm"></div>
                <p class="text-agro-700/70 mt-4 text-lg font-medium">Tecnologías y herramientas utilizadas</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <!-- Tarjetas de tecnología -->
                <?php
                $tecnologias = [
                    ['nombre' => 'PHP', 'version' => 'v8.2+', 'img' => 'https://www.php.net/images/logos/new-php-logo.svg', 'url' => 'https://www.php.net'],
                    ['nombre' => 'MySQL', 'version' => 'v8.0', 'img' => 'https://www.mysql.com/common/logos/mysql-logo.svg', 'url' => 'https://www.mysql.com'],
                    ['nombre' => 'SweetAlert2', 'version' => 'v11', 'img' => 'https://sweetalert2.github.io/images/SweetAlert2.png', 'url' => 'https://sweetalert2.github.io'],
                    ['nombre' => 'JavaScript', 'version' => 'ES6+', 'img' => 'https://upload.wikimedia.org/wikipedia/commons/6/6a/JavaScript-logo.png', 'url' => 'https://developer.mozilla.org/es/docs/Web/JavaScript'],
                    ['nombre' => 'Tailwind CSS', 'version' => 'v3', 'img' => 'https://upload.wikimedia.org/wikipedia/commons/d/d5/Tailwind_CSS_Logo.svg', 'url' => 'https://tailwindcss.com'],
                    ['nombre' => 'Laragon', 'version' => 'v6', 'img' => 'https://laragon.org/favicon.ico', 'url' => 'https://laragon.org'],
                    ['nombre' => 'VS Code', 'version' => 'Editor', 'img' => 'https://upload.wikimedia.org/wikipedia/commons/9/9a/Visual_Studio_Code_1.35_icon.svg', 'url' => 'https://code.visualstudio.com'],
                    ['nombre' => 'DomPDF', 'version' => 'Reportes PDF', 'img' => 'https://raw.githubusercontent.com/dompdf/dompdf/master/www/images/dompdf_logo_transparent.png', 'url' => 'https://github.com/dompdf/dompdf'],
                ];
                
                foreach ($tecnologias as $tec):
                ?>
                <div class="bg-agro-50/50 rounded-2xl border border-agro-100 shadow-sm p-8 flex flex-col items-center gap-4 transition-all hover:-translate-y-1 hover:shadow-md hover:bg-white hover:border-agro-200 group">
                    <img src="<?= $tec['img'] ?>" alt="<?= $tec['nombre'] ?>" class="h-16 object-contain group-hover:scale-110 transition-transform duration-300">
                    <div class="text-center">
                        <h4 class="font-bold text-agro-900 text-lg"><?= $tec['nombre'] ?></h4>
                        <span class="text-agro-400 font-medium text-sm"><?= $tec['version'] ?></span>
                    </div>
                    <a href="<?= $tec['url'] ?>" target="_blank" class="mt-2 w-full text-center bg-agro-100 text-agro-700 hover:bg-agro-600 hover:text-white text-sm font-bold py-2.5 rounded-xl transition-colors">
                        Más Info <i class="fas fa-arrow-up-right-from-square ml-1 text-[10px]"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</body>
</html>
