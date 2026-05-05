<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Gestión de Cultivos Agrícolas</title>
    <link href="img/icono-pagina.png" rel="icon" type="image/png">
    <link rel="stylesheet" href="css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        
        .hero-gradient {
            background: linear-gradient(135deg, rgba(15, 39, 29, 0.9) 0%, rgba(33, 84, 61, 0.7) 100%);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
        }

        .slide { opacity: 0; transition: opacity 1s ease-in-out; position: absolute; inset: 0; z-index: 0; }
        .slide.active { opacity: 1; z-index: 10; }
        
        .slide-content { opacity: 0; transform: translateY(20px); transition: all 0.8s ease-out 0.3s; }
        .slide.active .slide-content { opacity: 1; transform: translateY(0); }

        .hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-scale:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    </style>
</head>
<body class="bg-agro-50 text-gray-800 antialiased selection:bg-agro-300 selection:text-agro-900">

    <!-- Navegación -->
    <nav class="fixed top-0 left-0 w-full z-50 transition-all duration-300 py-2" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <div class="flex items-center gap-3 group cursor-pointer">
                    <div class="w-12 h-12 bg-white rounded-xl shadow-lg flex items-center justify-center p-2 group-hover:scale-105 transition-transform duration-300">
                        <img src="img/icono.png" alt="Logo SIGVOS" class="w-full h-full object-contain">
                    </div>
                    <span class="text-3xl font-heading font-extrabold tracking-tight text-white drop-shadow-md">
                        SIG<span class="text-agro-400">VOS</span>
                    </span>
                </div>
                <!-- Menú desktop -->
                <div class="hidden md:flex items-center space-x-8 font-medium text-white/90">
                    <a href="#inicio" class="hover:text-agro-300 transition-colors duration-200 text-sm tracking-wide uppercase">Inicio</a>
                    <a href="#que-es" class="hover:text-agro-300 transition-colors duration-200 text-sm tracking-wide uppercase">Plataforma</a>
                    <a href="#roles" class="hover:text-agro-300 transition-colors duration-200 text-sm tracking-wide uppercase">Roles</a>
                    <a href="#objetivos" class="hover:text-agro-300 transition-colors duration-200 text-sm tracking-wide uppercase">Beneficios</a>
                    <a href="../views/pages/desarrollador.php" class="hover:text-agro-300 transition-colors duration-200 text-sm tracking-wide uppercase">Desarrollador</a>
                    <a href="../views/auth/login.php" class="bg-agro-500 hover:bg-agro-400 text-white px-6 py-2.5 rounded-full transition-all duration-300 shadow-[0_0_15px_rgba(58,165,116,0.5)] hover:shadow-[0_0_25px_rgba(58,165,116,0.7)] text-sm font-bold tracking-wide uppercase border border-agro-400/50">
                        Acceder <i class="fas fa-arrow-right ml-2 text-xs"></i>
                    </a>
                </div>
                <!-- Botón móvil -->
                <button id="menu-toggle" class="md:hidden text-white focus:outline-none p-2" aria-label="Menú">
                    <i class="fas fa-bars text-2xl" id="menu-icon"></i>
                </button>
            </div>
        </div>
        <!-- Menú móvil -->
        <div id="mobile-menu" class="hidden md:hidden bg-agro-950/95 backdrop-blur-md border-t border-white/10 absolute w-full left-0 top-full">
            <div class="flex flex-col px-6 py-6 space-y-4 font-medium text-white/90">
                <a href="#inicio" class="hover:text-agro-300 transition py-2 border-b border-white/5" onclick="closeMobileMenu()">Inicio</a>
                <a href="#que-es" class="hover:text-agro-300 transition py-2 border-b border-white/5" onclick="closeMobileMenu()">Plataforma</a>
                <a href="#roles" class="hover:text-agro-300 transition py-2 border-b border-white/5" onclick="closeMobileMenu()">Roles</a>
                <a href="#objetivos" class="hover:text-agro-300 transition py-2 border-b border-white/5" onclick="closeMobileMenu()">Beneficios</a>
                <a href="../views/pages/desarrollador.php" class="hover:text-agro-300 transition py-2 border-b border-white/5" onclick="closeMobileMenu()">Desarrolladores</a>
                <a href="../views/auth/login.php" class="bg-agro-500 text-white px-5 py-3 rounded-xl text-center font-bold mt-4 shadow-lg">Ingresar al Sistema</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="inicio" class="relative h-screen min-h-[600px] overflow-hidden bg-agro-950">

        <!-- Slides -->
        <div class="slide active">
            <img src="https://images.unsplash.com/photo-1625246333195-78d9c38ad449?auto=format&fit=crop&w=2000&q=80" class="w-full h-full object-cover" alt="Cultivos">
            
            <!-- Overlay específico para este slide -->
            <div class="absolute inset-0 hero-gradient z-10"></div>
            
            <div class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-4 sm:px-6 lg:px-8 mt-10">
                <div class="slide-content max-w-4xl mx-auto">

                    <h1 class="text-5xl md:text-7xl font-heading font-bold text-white mb-6 leading-tight drop-shadow-lg">
                        Gestión Inteligente de <span class="text-transparent bg-clip-text bg-gradient-to-r from-agro-300 to-agro-500">Cultivos</span>
                    </h1>
                    <p class="text-lg md:text-2xl text-gray-100 mb-10 max-w-3xl mx-auto font-medium leading-relaxed drop-shadow-md">
                        Potencia tu agronegocio con SIGVOS. Control integral, trazabilidad total y decisiones basadas en datos para maximizar tu productividad.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <a href="../views/auth/login.php" class="bg-agro-500 hover:bg-agro-400 text-white px-8 py-4 rounded-full text-lg font-bold transition-all duration-300 shadow-[0_0_20px_rgba(58,165,116,0.4)] hover:shadow-[0_0_30px_rgba(58,165,116,0.6)] hover:-translate-y-1 w-full sm:w-auto">
                            Comenzar Ahora
                        </a>
                        <a href="#que-es" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white backdrop-blur-md px-8 py-4 rounded-full text-lg font-medium transition-all duration-300 hover:-translate-y-1 w-full sm:w-auto">
                            Conoce Más
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="slide">
            <img src="https://images.unsplash.com/photo-1592982537447-7440770cbfc9?auto=format&fit=crop&w=2000&q=80" class="w-full h-full object-cover" alt="Tecnología">
            
            <!-- Overlay específico para este slide -->
            <div class="absolute inset-0 hero-gradient z-10"></div>
            
            <div class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-4 sm:px-6 lg:px-8 mt-10">
                <div class="slide-content max-w-4xl mx-auto">

                    <h1 class="text-5xl md:text-7xl font-heading font-bold text-white mb-6 leading-tight drop-shadow-lg">
                        Trazabilidad en <span class="text-transparent bg-clip-text bg-gradient-to-r from-agro-300 to-agro-500">Tiempo Real</span>
                    </h1>
                    <p class="text-lg md:text-2xl text-gray-100 mb-10 max-w-3xl mx-auto font-medium leading-relaxed drop-shadow-md">
                        Registra actividades, monitorea el estado de cada lote y sincroniza tu información desde cualquier lugar, incluso sin conexión a internet.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <a href="../views/auth/login.php" class="bg-agro-500 hover:bg-agro-400 text-white px-8 py-4 rounded-full text-lg font-bold transition-all duration-300 shadow-[0_0_20px_rgba(58,165,116,0.4)] hover:shadow-[0_0_30px_rgba(58,165,116,0.6)] hover:-translate-y-1 w-full sm:w-auto">
                            Ingresar al Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="slide">
            <img src="https://images.unsplash.com/photo-1586771107445-d3ca888129ff?auto=format&fit=crop&w=2000&q=80" class="w-full h-full object-cover" alt="Trazabilidad">
            
            <!-- Overlay específico para este slide -->
            <div class="absolute inset-0 hero-gradient z-10"></div>
            
            <div class="absolute inset-0 z-20 flex flex-col items-center justify-center text-center px-4 sm:px-6 lg:px-8 mt-10">
                <div class="slide-content max-w-4xl mx-auto">

                    <h1 class="text-5xl md:text-7xl font-heading font-bold text-white mb-6 leading-tight drop-shadow-lg">
                        Aumenta tu <span class="text-transparent bg-clip-text bg-gradient-to-r from-agro-300 to-agro-500">Rentabilidad</span>
                    </h1>
                    <p class="text-lg md:text-2xl text-gray-100 mb-10 max-w-3xl mx-auto font-medium leading-relaxed drop-shadow-md">
                        Analiza la eficiencia de tu producción, gestiona a tu equipo de trabajo y optimiza tus costos con reportes detallados y precisos.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <a href="../views/auth/registro.php" class="bg-agro-500 hover:bg-agro-400 text-white px-8 py-4 rounded-full text-lg font-bold transition-all duration-300 shadow-[0_0_20px_rgba(58,165,116,0.4)] hover:shadow-[0_0_30px_rgba(58,165,116,0.6)] hover:-translate-y-1 w-full sm:w-auto">
                            Crea tu cuenta gratis
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controles Slider -->
        <div class="absolute bottom-10 left-0 w-full z-30 flex justify-center gap-3">
            <button class="slide-dot w-3 h-3 rounded-full bg-white/40 hover:bg-white transition-colors" onclick="goToSlide(0)" aria-label="Slide 1"></button>
            <button class="slide-dot w-3 h-3 rounded-full bg-white/40 hover:bg-white transition-colors" onclick="goToSlide(1)" aria-label="Slide 2"></button>
            <button class="slide-dot w-3 h-3 rounded-full bg-white/40 hover:bg-white transition-colors" onclick="goToSlide(2)" aria-label="Slide 3"></button>
        </div>
    </section>

    <!-- Sección Qué es SIGVOS -->
    <section id="que-es" class="py-24 relative overflow-hidden">
        <!-- Decoración de fondo -->
        <div class="absolute top-0 right-0 -mr-40 -mt-40 w-96 h-96 rounded-full bg-agro-100 opacity-50 blur-3xl z-0 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 -ml-40 -mb-40 w-96 h-96 rounded-full bg-agro-200 opacity-30 blur-3xl z-0 pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
            <div class="flex flex-col lg:flex-row items-center gap-16">
                <div class="lg:w-1/2">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1530836369250-ef72a3f5cda8?auto=format&fit=crop&w=1000&q=80" 
                             alt="Plataforma SIGVOS" 
                             class="rounded-3xl shadow-2xl z-10 relative object-cover h-[600px] w-full">
                        <div class="absolute -bottom-6 -right-6 w-48 h-48 bg-agro-500 rounded-3xl -z-10 opacity-20"></div>
                        <div class="absolute -top-6 -left-6 w-32 h-32 border-4 border-agro-300 rounded-full -z-10 opacity-40"></div>
                        
                        <!-- Floating Card -->
                        <div class="absolute bottom-10 -left-10 glass-card p-6 rounded-2xl animate-float hidden md:block">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-agro-100 flex items-center justify-center text-agro-600 text-xl">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 font-medium">Rendimiento</p>
                                    <p class="text-xl font-bold text-gray-800">+34%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="lg:w-1/2">
                    <h2 class="text-agro-600 font-bold tracking-wider uppercase text-sm mb-2">Innovación Agrícola</h2>
                    <h3 class="text-4xl md:text-5xl font-heading font-bold text-gray-900 mb-6 leading-tight">
                        Transformamos el campo con <span class="text-agro-500">tecnología</span>
                    </h3>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        SIGVOS es una plataforma integral diseñada para digitalizar la gestión de tus cultivos. Desde la planificación de la siembra hasta el análisis de la cosecha, te brindamos las herramientas necesarias para optimizar recursos y aumentar la rentabilidad.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex gap-4">
                            <div class="w-12 h-12 shrink-0 rounded-xl bg-agro-100 flex items-center justify-center text-agro-600 text-xl">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold text-gray-900 mb-1">Gestión Centralizada</h4>
                                <p class="text-gray-600">Controla todos tus lotes y variedades de cultivos desde un único panel intuitivo.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-12 h-12 shrink-0 rounded-xl bg-agro-100 flex items-center justify-center text-agro-600 text-xl">
                                <i class="fas fa-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <h4 class="text-xl font-bold text-gray-900 mb-1">Datos en Tiempo Real</h4>
                                <p class="text-gray-600">Sincronización instantánea de labores y reportes de campo, incluso en zonas de baja conectividad.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Roles del Sistema -->
    <section id="roles" class="py-24 bg-white border-y border-gray-100">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-agro-600 font-bold tracking-wider uppercase text-sm mb-2">Estructura de Trabajo</h2>
                <h3 class="text-4xl md:text-5xl font-heading font-bold text-gray-900 mb-6">Roles Especializados</h3>
                <p class="text-lg text-gray-600">El sistema se adapta a la estructura de tu equipo, brindando las herramientas exactas que cada rol necesita para brillar.</p>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <!-- Rol Admin -->
                <div class="glass-card bg-white p-10 rounded-3xl hover-scale border-t-4 border-t-agro-500 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-agro-50 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-150 duration-500 z-0 pointer-events-none"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-agro-500 text-white flex items-center justify-center text-2xl mb-6 shadow-lg shadow-agro-200">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-900 mb-4 font-heading">Administrador de Finca</h4>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            Posee la visión estratégica. Configura la finca, gestiona el personal, define los ciclos de cultivo y analiza los reportes financieros y productivos para tomar decisiones informadas.
                        </p>
                        <ul class="space-y-2 text-sm text-gray-600 font-medium">
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Planificación de lotes y cultivos</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Análisis de costos y rentabilidad</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Generación de reportes gerenciales</li>
                        </ul>
                    </div>
                </div>

                <!-- Rol Operario -->
                <div class="glass-card bg-white p-10 rounded-3xl hover-scale border-t-4 border-t-agro-400 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-agro-50 rounded-full -mr-10 -mt-10 transition-transform group-hover:scale-150 duration-500 z-0 pointer-events-none"></div>
                    <div class="relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-agro-400 text-white flex items-center justify-center text-2xl mb-6 shadow-lg shadow-agro-200">
                            <i class="fas fa-tractor"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-900 mb-4 font-heading">Trabajador de Campo</h4>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            El motor operativo. Registra las labores diarias directamente desde el terreno, reporta novedades fitosanitarias y mantiene actualizada la bitácora de actividades del cultivo.
                        </p>
                        <ul class="space-y-2 text-sm text-gray-600 font-medium">
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Registro rápido de labores</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Reporte de plagas o enfermedades</li>
                            <li class="flex items-center gap-2"><i class="fas fa-check text-agro-500"></i> Interfaz simplificada y directa</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Beneficios / Objetivos -->
    <section id="objetivos" class="py-24 bg-agro-950 text-white relative">
        <div class="absolute inset-0 opacity-10 pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        
        <div class="max-w-7xl mx-auto px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-agro-400 font-bold tracking-wider uppercase text-sm mb-2">Nuestra Propuesta de Valor</h2>
                <h3 class="text-4xl md:text-5xl font-heading font-bold mb-6">¿Por qué elegir SIGVOS?</h3>
                <p class="text-lg text-gray-300">Convertimos los datos del campo en conocimiento útil para impulsar el crecimiento sostenible de tu producción.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 p-8 rounded-2xl hover:bg-white/10 transition-colors">
                    <div class="w-14 h-14 rounded-full bg-agro-500/20 text-agro-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 font-heading">Organización Total</h4>
                    <p class="text-gray-400 text-sm leading-relaxed">Estructuración detallada de fincas, lotes y ciclos de cultivo para una gestión ordenada y sin confusiones.</p>
                </div>

                <div class="bg-white/5 backdrop-blur-sm border border-white/10 p-8 rounded-2xl hover:bg-white/10 transition-colors">
                    <div class="w-14 h-14 rounded-full bg-agro-500/20 text-agro-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 font-heading">Decisiones Basadas en Datos</h4>
                    <p class="text-gray-400 text-sm leading-relaxed">Métricas, gráficos y reportes exportables que revelan el verdadero rendimiento y rentabilidad de cada lote.</p>
                </div>

                <div class="bg-white/5 backdrop-blur-sm border border-white/10 p-8 rounded-2xl hover:bg-white/10 transition-colors">
                    <div class="w-14 h-14 rounded-full bg-agro-500/20 text-agro-400 flex items-center justify-center text-2xl mb-6">
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <h4 class="text-xl font-bold mb-3 font-heading">Trazabilidad Segura</h4>
                    <p class="text-gray-400 text-sm leading-relaxed">Registro inmutable de todas las acciones, insumos aplicados y cosechas, vital para certificaciones agrícolas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#0b1a13] text-gray-300 pt-20 pb-10 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-12 mb-16">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-3 mb-6">
                        <img src="img/icono.png" alt="Logo SIGVOS" class="w-10 h-10 brightness-0 invert opacity-90">
                        <span class="text-2xl font-heading font-extrabold tracking-tight text-white">
                            SIG<span class="text-agro-400">VOS</span>
                        </span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed max-w-sm mb-8">
                        Innovando en la gestión agrícola. Software especializado para potenciar la productividad y sostenibilidad del agro moderno.
                    </p>
                    <div class="flex space-x-4">
                        <a href="https://www.facebook.com/" target="_blank" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#1877F2] hover:text-white transition-all text-gray-400">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#E4405F] hover:text-white transition-all text-gray-400">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://web.whatsapp.com/" target="_blank" class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center hover:bg-[#25D366] hover:text-white transition-all text-gray-400">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>

                <div>
                    <h4 class="text-white font-bold mb-6 uppercase text-sm tracking-wider">Explorar</h4>
                    <ul class="space-y-4 text-sm text-gray-400">
                        <li><a href="#inicio" class="hover:text-agro-400 transition-colors">Inicio</a></li>
                        <li><a href="#que-es" class="hover:text-agro-400 transition-colors">Plataforma</a></li>
                        <li><a href="#roles" class="hover:text-agro-400 transition-colors">Roles</a></li>
                        <li><a href="#objetivos" class="hover:text-agro-400 transition-colors">Beneficios</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-bold mb-6 uppercase text-sm tracking-wider">Contacto</h4>
                    <ul class="space-y-4 text-sm text-gray-400">
                        <li class="flex items-start gap-3">
                            <i class="fas fa-envelope mt-1 text-agro-400"></i>
                            <span>sigvos.app@gmail.com</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <i class="fas fa-map-marker-alt mt-1 text-agro-400"></i>
                            <span>Vereda Palomas<br>Colombia</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-500">
                <p>&copy; 2026 SIGVOS. Todos los derechos reservados.</p>
                <div class="flex gap-4">
                    <a href="../views/auth/login.php" class="hover:text-white transition-colors">Panel de Control</a>
                    <a href="../views/pages/desarrollador.php" class="hover:text-white transition-colors">Desarrolladores</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Navbar Scroll Effect
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('bg-agro-950/95', 'backdrop-blur-md', 'border-b', 'border-white/10', 'py-0');
                navbar.classList.remove('py-2');
            } else {
                navbar.classList.remove('bg-agro-950/95', 'backdrop-blur-md', 'border-b', 'border-white/10', 'py-0');
                navbar.classList.add('py-2');
            }
        });

        // Hero Slider
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.slide-dot');

        function updateSlider() {
            slides.forEach(s => s.classList.remove('active'));
            dots.forEach(d => {
                d.classList.remove('bg-white', 'scale-125');
                d.classList.add('bg-white/40');
            });
            
            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.remove('bg-white/40');
            dots[currentSlide].classList.add('bg-white', 'scale-125');
        }

        function goToSlide(index) {
            currentSlide = index;
            updateSlider();
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % slides.length;
            updateSlider();
        }

        setInterval(nextSlide, 6000);
        updateSlider(); // Init

        // Mobile Menu
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.getElementById('menu-icon');

        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            menuIcon.className = mobileMenu.classList.contains('hidden')
                ? 'fas fa-bars text-2xl'
                : 'fas fa-times text-2xl';
        });

        function closeMobileMenu() {
            mobileMenu.classList.add('hidden');
            menuIcon.className = 'fas fa-bars text-2xl';
        }
    </script>
</body>
</html>