<?php
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/session.php';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGVOS | Registro de Productor</title>
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
            left: 1rem;
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

    <div class="glass-card w-full max-w-6xl rounded-3xl overflow-hidden flex flex-col lg:flex-row min-h-[700px] animate-fade-in-up border border-agro-100 shadow-[0_20px_50px_rgba(33,84,61,0.1)]">

        <!-- Panel izquierdo con imagen (2/5) -->
        <div class="hidden lg:flex lg:w-5/12 relative overflow-hidden">
            <!-- Imagen de fondo -->
            <img src="https://images.unsplash.com/photo-1586771107445-d3ca888129ff?auto=format&fit=crop&w=1000&q=80" alt="Cultivos Agrícolas" class="absolute inset-0 w-full h-full object-cover">
            
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
                        Inicia tu camino hacia la <span class="text-agro-400">innovación</span>
                    </h2>
                    <p class="text-agro-100 text-base leading-relaxed font-light drop-shadow mb-8">
                        Únete a la plataforma líder en gestión y trazabilidad de cultivos. Digitaliza tu finca de manera rápida y segura.
                    </p>
                    
                    <div class="space-y-5">
                        <div class="flex items-center gap-4 text-sm font-medium text-agro-50">
                            <div class="w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
                                <i class="fas fa-check text-agro-400"></i>
                            </div>
                            Registro rápido y seguro
                        </div>
                        <div class="flex items-center gap-4 text-sm font-medium text-agro-50">
                            <div class="w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
                                <i class="fas fa-shield-alt text-agro-400"></i>
                            </div>
                            Protección de datos garantizada
                        </div>
                        <div class="flex items-center gap-4 text-sm font-medium text-agro-50">
                            <div class="w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
                                <i class="fas fa-mobile-alt text-agro-400"></i>
                            </div>
                            Acceso desde cualquier dispositivo
                        </div>
                    </div>
                </div>

                <div class="mt-8 border-t border-white/10 pt-6">
                    <p class="text-sm text-agro-200 mb-2">¿Ya formas parte de SIGVOS?</p>
                    <a href="login.php" class="inline-flex items-center gap-2 text-white font-bold hover:text-agro-300 transition-colors group">
                        Inicia sesión aquí <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Panel derecho (formulario) (3/5) -->
        <div class="w-full lg:w-7/12 p-8 md:p-12 flex flex-col justify-center bg-white relative">

            <!-- Decoración sutil de fondo -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-agro-50 rounded-full -mr-32 -mt-32 opacity-50 pointer-events-none"></div>

            <div class="relative z-10 max-w-2xl mx-auto w-full">
                <!-- Header móvil -->
                <div class="flex lg:hidden items-center mb-8 gap-3">
                        <img src="../../public/img/icono.png" alt="Logo SIGVOS" class="w-6 h-6 object-contain">
                    <span class="text-2xl font-heading font-extrabold tracking-tight text-gray-900">
                        SIG<span class="text-agro-500">VOS</span>
                    </span>
                </div>

                <div class="mb-8">
                    <h1 class="text-3xl font-heading font-bold text-gray-900 mb-2">Crea tu cuenta de productor</h1>
                    <p class="text-gray-500 text-sm">Completa tus datos para configurar tu espacio de trabajo agrícola.</p>
                </div>

                <!-- Alertas SweetAlert2 -->
                <?php if ($error): ?>
                <script>
                document.addEventListener('DOMContentLoaded', () => Swal.fire({
                    icon: 'error', 
                    title: 'Error en el registro',
                    text: '<?= addslashes(htmlspecialchars($error)) ?>',
                    confirmButtonColor: '#25694a', /* agro-700 */
                    confirmButtonText: 'Corregir',
                    customClass: { popup: 'rounded-2xl font-sans' }
                }));
                </script>
                <?php endif; ?>

                <form id="registerForm" action="../../controllers/UsuarioController.php" method="POST" class="space-y-5">
                    <input type="hidden" name="accion" value="registro">
                    <?= csrf_field() ?>

                    <!-- Nombres y Apellidos -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="input-group">
                            <input type="text" name="nombre" id="nombre" required maxlength="80" autocomplete="given-name" placeholder=" "
                                class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="nombre">Nombres</label>
                        </div>
                        <div class="input-group">
                            <input type="text" name="apellidos" id="apellidos" required maxlength="80" autocomplete="family-name" placeholder=" "
                                class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="apellidos">Apellidos</label>
                        </div>
                    </div>

                    <!-- Correo y Teléfono -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="input-group relative">
                            <input type="email" name="correo" id="correo" required maxlength="150" autocomplete="email" placeholder=" "
                                class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="correo">Correo Electrónico</label>
                        </div>
                        <div class="input-group relative">
                            <input type="tel" name="telefono" id="telefono" required maxlength="20" autocomplete="tel" placeholder=" "
                                class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="telefono">Teléfono de contacto</label>
                        </div>
                    </div>

                    <!-- Contraseñas -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="input-group relative">
                            <input type="password" name="contrasena" id="pass1" required autocomplete="new-password" placeholder=" "
                                class="w-full pl-4 pr-12 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="pass1">Contraseña</label>
                            <button type="button" onclick="togglePass('pass1','eye1')" aria-label="Mostrar contraseña"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-agro-600 transition-colors z-10 focus:outline-none">
                                <i class="fas fa-eye text-sm" id="eye1"></i>
                            </button>
                        </div>
                        <div class="input-group relative">
                            <input type="password" name="confirmar" id="pass2" required autocomplete="new-password" placeholder=" "
                                class="w-full pl-4 pr-12 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none transition-all text-sm text-gray-900 font-medium">
                            <label for="pass2">Confirmar Contraseña</label>
                            <button type="button" onclick="togglePass('pass2','eye2')" aria-label="Mostrar contraseña"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-agro-600 transition-colors z-10 focus:outline-none">
                                <i class="fas fa-eye text-sm" id="eye2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Términos -->
                    <div class="pt-2 pb-1">
                        <label class="flex items-start gap-3 cursor-pointer select-none group">
                            <div class="relative flex items-center justify-center mt-0.5">
                                <input type="checkbox" name="terms" required class="peer appearance-none w-5 h-5 border-2 border-gray-300 rounded-md checked:bg-agro-500 checked:border-agro-500 transition-all cursor-pointer focus:ring-2 focus:ring-agro-500/30 outline-none">
                                <i class="fas fa-check absolute text-white text-xs opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                            </div>
                            <span class="text-sm text-gray-500 leading-relaxed group-hover:text-gray-700 transition-colors">
                                He leído y acepto los <a href="#" class="text-agro-600 font-semibold hover:underline hover:text-agro-700">Términos de Servicio</a> y la <a href="#" class="text-agro-600 font-semibold hover:underline hover:text-agro-700">Política de Privacidad</a> de SIGVOS.
                            </span>
                        </label>
                    </div>

                    <!-- Botón submit -->
                    <button type="submit" id="submitBtn"
                        class="w-full bg-agro-600 hover:bg-agro-500 text-white font-bold py-4 rounded-xl transition-all duration-300 shadow-[0_0_15px_rgba(43,132,90,0.3)] hover:shadow-[0_0_25px_rgba(43,132,90,0.5)] flex items-center justify-center gap-2 mt-2 hover:-translate-y-1">
                        <span id="btnText" class="tracking-wide">Crear mi cuenta</span>
                        <i class="fas fa-arrow-right text-sm" id="btnIcon"></i>
                        <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    </button>
                    
                    <div class="mt-6 flex justify-center lg:hidden">
                        <p class="text-sm text-gray-500">
                            ¿Ya tienes cuenta? 
                            <a href="login.php" class="text-agro-600 font-bold hover:underline">Inicia sesión</a>
                        </p>
                    </div>

                    <div class="mt-8 flex justify-center">
                        <a href="../../public/index.php" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-agro-600 transition-colors group">
                            <span class="w-8 h-8 rounded-full bg-gray-50 border border-gray-200 flex items-center justify-center mr-3 group-hover:bg-agro-50 group-hover:border-agro-200 transition-colors">
                                <i class="fas fa-arrow-left text-xs group-hover:-translate-x-1 transition-transform"></i>
                            </span>
                            Volver al inicio
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Toggle mostrar/ocultar contraseña
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.className = show ? 'fas fa-eye-slash text-sm' : 'fas fa-eye text-sm';
        }

        // Loading state al hacer submit
        document.getElementById('registerForm').addEventListener('submit', function() {
            // Check if passwords match before showing loading
            const pass1 = document.getElementById('pass1').value;
            const pass2 = document.getElementById('pass2').value;
            
            if (pass1 === pass2 && this.checkValidity()) {
                const btn     = document.getElementById('submitBtn');
                const text    = document.getElementById('btnText');
                const icon    = document.getElementById('btnIcon');
                const spinner = document.getElementById('btnSpinner');

                btn.disabled      = true;
                btn.classList.add('opacity-90', 'cursor-not-allowed', 'hover:-translate-y-0', 'hover:shadow-none');
                text.textContent  = 'Registrando...';
                icon.classList.add('hidden');
                spinner.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
