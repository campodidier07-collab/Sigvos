<?php
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/session.php';
$error = $_SESSION['error'] ?? '';
$exito = $_SESSION['exito'] ?? '';
unset($_SESSION['error'], $_SESSION['exito']);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Acceso al Sistema</title>
    <link rel="icon" href="../../public/img/icono-pagina.png" type="image/png">
    <link rel="stylesheet" href="../../public/css/output.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f2fbf5; }
        h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Outfit', sans-serif; }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.05);
        }

        .image-overlay {
            background: linear-gradient(135deg, rgba(15, 39, 29, 0.85) 0%, rgba(33, 84, 61, 0.6) 100%);
        }

        /* Label flotante mejorado */
        .input-group { position: relative; }
        .input-group input { padding-top: 1.5rem; padding-bottom: 0.5rem; }
        .input-group label {
            position: absolute;
            left: 2.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.875rem;
            color: #9ca3af;
            pointer-events: none;
            transition: all 0.2s ease;
        }
        .input-group input:focus ~ label,
        .input-group input:not(:placeholder-shown) ~ label {
            top: 0.65rem;
            transform: translateY(0);
            font-size: 0.65rem;
            font-weight: 700;
            color: #3aa574; /* agro-500 */
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .input-group input:focus {
            border-color: #3aa574;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(58, 165, 116, 0.1);
        }

        /* Patrón de fondo sutil para toda la página */
        .bg-pattern {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%233aa574' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-8 bg-pattern antialiased selection:bg-agro-300 selection:text-agro-900">

    <div class="glass-card w-full max-w-6xl rounded-3xl overflow-hidden flex flex-col md:flex-row min-h-[700px] animate-fade-in-up border border-agro-100 shadow-[0_20px_50px_rgba(33,84,61,0.1)]">

        <!-- Panel izquierdo con imagen -->
        <div class="hidden md:flex md:w-1/2 relative overflow-hidden">
            <!-- Imagen de fondo -->
            <img src="https://images.unsplash.com/photo-1592982537447-7440770cbfc9?auto=format&fit=crop&w=1000&q=80" alt="Cultivos Agrícolas" class="absolute inset-0 w-full h-full object-cover">
            
            <!-- Overlay degradado -->
            <div class="absolute inset-0 image-overlay"></div>
            
            <!-- Contenido -->
            <div class="relative z-10 p-12 flex flex-col justify-between h-full text-white">
                <div>
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-12 h-12 bg-white/10 backdrop-blur-md rounded-xl border border-white/20 flex items-center justify-center shadow-lg">
                            <img src="../../public/img/icono.png" alt="Logo SIGVOS" class="w-8 h-8 object-contain">
                        </div>
                        <span class="text-3xl font-heading font-extrabold tracking-tight drop-shadow-md">
                            SIG<span class="text-agro-400">VOS</span>
                        </span>
                    </div>
                    
                    <h2 class="text-4xl font-heading font-bold mb-6 leading-tight drop-shadow-lg">
                        El centro de control de tu <span class="text-agro-400">agronegocio</span>
                    </h2>
                    <p class="text-agro-100 text-lg leading-relaxed max-w-md font-light drop-shadow">
                        Accede a tus datos, gestiona labores de campo y monitorea la rentabilidad de cada lote en tiempo real.
                    </p>
                </div>

                <!-- Stats / Info -->
                <div class="grid grid-cols-2 gap-4 mt-8">
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 animate-float" style="animation-delay: 0s;">
                        <div class="text-agro-300 mb-2"><i class="fas fa-chart-line text-2xl"></i></div>
                        <h4 class="font-bold text-lg">Productividad</h4>
                        <p class="text-xs text-agro-100 opacity-80">Métricas exactas de cosecha</p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 animate-float" style="animation-delay: 1s;">
                        <div class="text-agro-300 mb-2"><i class="fas fa-seedling text-2xl"></i></div>
                        <h4 class="font-bold text-lg">Trazabilidad</h4>
                        <p class="text-xs text-agro-100 opacity-80">Historial completo del lote</p>
                    </div>
                </div>
                
                <div class="mt-8 flex items-center gap-3 bg-black/20 backdrop-blur-sm p-4 rounded-2xl border border-white/10">
                        <img src="../../public/img/icono-pagina.png" alt="Seguridad" class="w-6 h-6 object-contain">
                    <p class="text-xs text-agro-50/80 leading-relaxed font-medium">Conexión segura y cifrada. Tus datos agrícolas están protegidos.</p>
                </div>
            </div>
        </div>

        <!-- Panel derecho (formulario) -->
        <div class="w-full md:w-1/2 p-8 md:p-14 flex flex-col justify-center bg-white relative">

            <!-- Decoración sutil de fondo -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-agro-50 rounded-full -mr-32 -mt-32 opacity-50 pointer-events-none"></div>

            <div class="relative z-10">
                <!-- Header móvil -->
                <div class="flex md:hidden items-center mb-10 gap-3">
                    <div class="w-12 h-12 bg-agro-50 rounded-xl border border-agro-100 flex items-center justify-center shadow-sm">
                        <img src="../../public/img/icono.png" alt="Logo SIGVOS" class="w-8 h-8 object-contain">
                    </div>
                    <span class="text-3xl font-heading font-extrabold tracking-tight text-gray-900">
                        SIG<span class="text-agro-500">VOS</span>
                    </span>
                </div>

                <div class="mb-10">
                    <h1 class="text-3xl font-heading font-bold text-gray-900 mb-2">Bienvenido de vuelta</h1>
                    <p class="text-gray-500 text-base">Ingresa tus credenciales para acceder al panel</p>
                </div>

                <!-- Alertas SweetAlert2 -->
                <?php if ($error): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => Swal.fire({
                    icon: 'error', 
                    title: 'Acceso denegado',
                    text: '<?= addslashes(htmlspecialchars($error)) ?>',
                    confirmButtonColor: '#25694a', /* agro-700 */
                    confirmButtonText: 'Intentar de nuevo',
                    customClass: { popup: 'rounded-2xl font-sans' }
                }));
                </script>
                <?php endif; ?>
                <?php if ($exito): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => Swal.fire({
                    icon: 'success', 
                    title: '¡Listo!',
                    text: '<?= addslashes(htmlspecialchars($exito)) ?>',
                    confirmButtonColor: '#3aa574', /* agro-500 */
                    timer: 3000, 
                    timerProgressBar: true,
                    customClass: { popup: 'rounded-2xl font-sans' }
                }));
                </script>
                <?php endif; ?>

                <form id="loginForm" action="../../controllers/UsuarioController.php" method="POST" class="space-y-6">
                    <input type="hidden" name="accion" value="login">
                    <?= csrf_field() ?>

                    <!-- Email -->
                    <div class="input-group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 z-10 pointer-events-none">
                            <i class="fas fa-envelope text-sm"></i>
                        </span>
                        <input type="email" name="correo" id="correo" placeholder=" " required
                            class="w-full pl-11 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                        <label for="correo">Correo Electrónico</label>
                    </div>

                    <!-- Contraseña -->
                    <div class="input-group">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 z-10 pointer-events-none">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="contrasena" id="contrasena" placeholder=" " required
                            class="w-full pl-11 pr-12 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                        <label for="contrasena">Contraseña</label>
                        <!-- Toggle ojo -->
                        <button type="button" id="togglePass" aria-label="Mostrar contraseña"
                            class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-agro-600 transition-colors z-10 focus:outline-none">
                            <i class="fas fa-eye text-sm" id="eyeIcon"></i>
                        </button>
                    </div>

                    <!-- Recordar / Olvidé -->
                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 cursor-pointer select-none group">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" name="remember" id="remember" class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded-md checked:bg-agro-500 checked:border-agro-500 transition-all cursor-pointer focus:ring-2 focus:ring-agro-500/30 outline-none">
                                <i class="fas fa-check absolute text-white text-xs opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                            </div>
                            <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Recordar mi sesión</span>
                        </label>
                        <a href="#" class="text-sm text-agro-600 font-semibold hover:text-agro-700 hover:underline transition-colors">¿Olvidaste tu contraseña?</a>
                    </div>

                    <!-- Botón submit -->
                    <button type="submit" id="submitBtn"
                        class="w-full bg-agro-600 hover:bg-agro-500 text-white font-bold py-4 rounded-xl transition-all duration-300 shadow-[0_0_15px_rgba(43,132,90,0.3)] hover:shadow-[0_0_25px_rgba(43,132,90,0.5)] flex items-center justify-center gap-2 mt-4 hover:-translate-y-1">
                        <span id="btnText" class="tracking-wide">Ingresar al Panel</span>
                        <i class="fas fa-arrow-right text-sm" id="btnIcon"></i>
                        <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    </button>
                </form>

                <!-- Divisor -->
                <div class="relative flex py-8 items-center">
                    <div class="flex-grow border-t border-gray-200"></div>
                    <span class="flex-shrink mx-4 text-gray-400 text-xs font-semibold uppercase tracking-widest">O</span>
                    <div class="flex-grow border-t border-gray-200"></div>
                </div>

                <p class="text-center text-sm text-gray-600">
                    ¿Aún no tienes una cuenta?
                    <a href="registro.php" class="text-agro-600 font-bold hover:underline hover:text-agro-700 transition-colors ml-1">Regístrate como productor</a>
                </p>

                <div class="mt-8 flex justify-center">
                    <a href="../../public/index.php" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-agro-600 transition-colors group">
                        <span class="w-8 h-8 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center mr-3 group-hover:bg-agro-50 group-hover:border-agro-200 transition-colors">
                            <i class="fas fa-arrow-left text-xs group-hover:-translate-x-1 transition-transform"></i>
                        </span>
                        Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle mostrar/ocultar contraseña
        const togglePass = document.getElementById('togglePass');
        const contrasena = document.getElementById('contrasena');
        const eyeIcon    = document.getElementById('eyeIcon');

        togglePass.addEventListener('click', () => {
            const isPassword = contrasena.type === 'password';
            contrasena.type  = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'fas fa-eye-slash text-sm' : 'fas fa-eye text-sm';
        });

        // Loading state al hacer submit
        document.getElementById('loginForm').addEventListener('submit', () => {
            const btn     = document.getElementById('submitBtn');
            const text    = document.getElementById('btnText');
            const icon    = document.getElementById('btnIcon');
            const spinner = document.getElementById('btnSpinner');

            btn.disabled      = true;
            btn.classList.add('opacity-90', 'cursor-not-allowed', 'hover:-translate-y-0', 'hover:shadow-none');
            text.textContent  = 'Autenticando...';
            icon.classList.add('hidden');
            spinner.classList.remove('hidden');
        });
        // Olvidé contraseña
        function olvidePassword() {
            Swal.fire({
                title: 'Recuperar Contraseña',
                text: 'Comunícate con el administrador del sistema (admin@sigvos.com) para que restablezca tu contraseña desde el panel de control.',
                icon: 'info',
                confirmButtonColor: '#25694a',
                confirmButtonText: 'Entendido',
                customClass: { popup: 'rounded-2xl font-sans' }
            });
        }
    </script>
</body>
</html>
