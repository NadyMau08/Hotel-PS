/**
 * M�dulo de gesti�n de usuarios - JavaScript avanzado
 */

class UsuariosManager {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupValidation();
    }

    init() {
        // Configuraci�n inicial
        this.table = null;
        this.currentUserId = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // Inicializar DataTable
        this.initDataTable();
        
        // Inicializar tooltips
        this.initTooltips();
        
        // Configurar auto-refresh
        this.setupAutoRefresh();
    }

    initDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaUsuarios')) {
            $('#tablaUsuarios').DataTable().destroy();
        }

        this.table = $('#tablaUsuarios').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            },
            responsive: true,
            order: [[0, 'desc']],
            columnDefs: [
                { targets: [8], orderable: false },
                { targets: [0], visible: false }
            ],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            drawCallback: function() {
                // Reinicializar tooltips despu�s de cada redibujado
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        });
    }

    initTooltips() {
        // Inicializar todos los tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    setupAutoRefresh() {
        // Auto-refresh cada 5 minutos
        setInterval(() => {
            if (!$('.modal').hasClass('show')) {
                this.refreshStats();
            }
        }, 300000);
    }

    setupEventListeners() {
        // Eventos para formularios
        this.setupFormEvents();
        
        // Eventos para validaci�n en tiempo real
        this.setupRealTimeValidation();
        
        // Eventos para filtros
        this.setupFilters();
        
        // Eventos para b�squeda
        this.setupSearch();
    }

    setupFormEvents() {
        // Crear usuario
        $('#formCrearUsuario').on('submit', (e) => {
            e.preventDefault();
            this.crearUsuario();
        });

        // Editar usuario
        $('#formEditarUsuario').on('submit', (e) => {
            e.preventDefault();
            this.editarUsuario();
        });

        // Cambiar contrase�a
        $('#formCambiarContrase�a').on('submit', (e) => {
            e.preventDefault();
            this.cambiarContrase�a();
        });

        // Generar contrase�a autom�tica
        $('#generarContrase�a').on('click', () => {
            this.generarContrase�a();
        });

        // Limpiar formularios al cerrar modales
        $('.modal').on('hidden.bs.modal', function() {
            $(this).find('form')[0].reset();
            $(this).find('.is-invalid').removeClass('is-invalid');
            $(this).find('.is-valid').removeClass('is-valid');
        });
    }

    setupRealTimeValidation() {
        // Validaci�n de usuario en tiempo real
        $('#crear_usuario').on('input', debounce((e) => {
            this.validateUsername(e.target);
        }, 500));

        // Validaci�n de correo en tiempo real
        $('#crear_correo, #editar_correo').on('input', debounce((e) => {
            this.validateEmail(e.target);
        }, 500));

        // Validaci�n de contrase�a en tiempo real
        $('#crear_contrase�a, #nueva_contrase�a').on('input', (e) => {
            this.validatePassword(e.target);
        });

        // Confirmar contrase�a
        $('#confirmar_contrase�a').on('input', (e) => {
            this.validatePasswordConfirm(e.target);
        });
    }

    setupValidation() {
        // Configurar validaci�n personalizada
        this.setupCustomValidation();
    }

    setupCustomValidation() {
        // Validaci�n personalizada para Bootstrap
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    }

    setupFilters() {
        // Filtro por rol
        $('#filtroRol').on('change', () => {
            const valor = $('#filtroRol').val();
            this.table.column(5).search(valor).draw();
        });

        // Filtro por estado
        $('#filtroEstado').on('change', () => {
            const valor = $('#filtroEstado').val();
            this.table.column(6).search(valor).draw();
        });

        // Limpiar filtros
        $('#limpiarFiltros').on('click', () => {
            $('#filtroRol, #filtroEstado').val('');
            this.table.search('').columns().search('').draw();
        });
    }

    setupSearch() {
        // B�squeda global mejorada
        $('#busquedaGlobal').on('keyup', debounce((e) => {
            this.table.search(e.target.value).draw();
        }, 300));
    }

    // M�todos para operaciones CRUD
    async crearUsuario() {
        const formData = new FormData(document.getElementById('formCrearUsuario'));
        formData.append('action', 'crear');
        formData.append('csrf_token', this.csrfToken);

        try {
            this.showLoading('#formCrearUsuario button[type="submit"]');
            
            const response = await this.makeRequest('usuarios.php', formData);
            
            if (response.success) {
                await Swal.fire({
                    icon: 'success',
                    title: '��xito!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                $('#modalCrearUsuario').modal('hide');
                this.refreshTable();
                this.refreshStats();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error de conexi�n');
        } finally {
            this.hideLoading('#formCrearUsuario button[type="submit"]');
        }
    }

    async editarUsuario() {
        const formData = new FormData(document.getElementById('formEditarUsuario'));
        formData.append('action', 'editar');
        formData.append('csrf_token', this.csrfToken);

        try {
            this.showLoading('#formEditarUsuario button[type="submit"]');
            
            const response = await this.makeRequest('usuarios.php', formData);
            
            if (response.success) {
                await Swal.fire({
                    icon: 'success',
                    title: '��xito!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                $('#modalEditarUsuario').modal('hide');
                this.refreshTable();
                this.refreshStats();
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error de conexi�n');
        } finally {
            this.hideLoading('#formEditarUsuario button[type="submit"]');
        }
    }

    async cambiarContrase�a() {
        const formData = new FormData(document.getElementById('formCambiarContrase�a'));
        formData.append('action', 'cambiar_contrase�a');
        formData.append('csrf_token', this.csrfToken);

        const nuevaContrase�a = $('#nueva_contrase�a').val();
        const confirmarContrase�a = $('#confirmar_contrase�a').val();

        if (nuevaContrase�a !== confirmarContrase�a) {
            this.showError('Las contrase�as no coinciden');
            return;
        }

        try {
            this.showLoading('#formCambiarContrase�a button[type="submit"]');
            
            const response = await this.makeRequest('usuarios.php', formData);
            
            if (response.success) {
                await Swal.fire({
                    icon: 'success',
                    title: '��xito!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                $('#modalCambiarContrase�a').modal('hide');
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error de conexi�n');
        } finally {
            this.hideLoading('#formCambiarContrase�a button[type="submit"]');
        }
    }

    async obtenerUsuario(id) {
        const formData = new FormData();
        formData.append('action', 'obtener_usuario');
        formData.append('id', id);
        formData.append('csrf_token', this.csrfToken);

        try {
            const response = await this.makeRequest('usuarios.php', formData);
            
            if (response.success) {
                return response.usuario;
            } else {
                this.showError(response.message);
                return null;
            }
        } catch (error) {
            this.showError('Error de conexi�n');
            return null;
        }
    }

    async eliminarUsuario(id, nombre) {
        const result = await Swal.fire({
            title: '�Est�s seguro?',
            text: `�Deseas eliminar al usuario "${nombre}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'S�, eliminar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'eliminar');
            formData.append('id', id);
            formData.append('csrf_token', this.csrfToken);

            try {
                const response = await this.makeRequest('usuarios.php', formData);
                
                if (response.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '�Eliminado!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    this.refreshTable();
                    this.refreshStats();
                } else {
                    this.showError(response.message);
                }
            } catch (error) {
                this.showError('Error de conexi�n');
            }
        }
    }

    // M�todos de validaci�n
    async validateUsername(input) {
        const username = input.value.trim();
        
        if (username.length < 3) {
            this.setValidationState(input, false, 'El usuario debe tener al menos 3 caracteres');
            return;
        }

        if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
            this.setValidationState(input, false, 'Solo se permiten letras, n�meros, puntos, guiones y guiones bajos');
            return;
        }

        // Verificar disponibilidad (solo en creaci�n)
        if (input.id === 'crear_usuario') {
            try {
                const formData = new FormData();
                formData.append('action', 'verificar_usuario');
                formData.append('usuario', username);
                
                const response = await this.makeRequest('usuarios.php', formData);
                
                if (response.disponible) {
                    this.setValidationState(input, true, 'Usuario disponible');
                } else {
                    this.setValidationState(input, false, 'Este usuario ya est� en uso');
                }
            } catch (error) {
                console.log('Error al verificar usuario:', error);
            }
        } else {
            this.setValidationState(input, true);
        }
    }

    validateEmail(input) {
        const email = input.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(email)) {
            this.setValidationState(input, false, 'Ingresa un correo electr�nico v�lido');
        } else {
            this.setValidationState(input, true);
        }
    }

    validatePassword(input) {
        const password = input.value;
        const validation = this.checkPasswordStrength(password);

        this.setValidationState(input, validation.isValid, validation.message);
        this.updatePasswordStrength(input, validation);
    }

    validatePasswordConfirm(input) {
        const password = $('#nueva_contrase�a').val();
        const confirm = input.value;

        if (password !== confirm) {
            this.setValidationState(input, false, 'Las contrase�as no coinciden');
        } else {
            this.setValidationState(input, true);
        }
    }

    checkPasswordStrength(password) {
        let score = 0;
        let feedback = [];

        if (password.length >= 8) score++;
        else feedback.push('Al menos 8 caracteres');

        if (/[a-z]/.test(password)) score++;
        else feedback.push('Una letra min�scula');

        if (/[A-Z]/.test(password)) score++;
        else feedback.push('Una letra may�scula');

        if (/[0-9]/.test(password)) score++;
        else feedback.push('Un n�mero');

        if (/[^a-zA-Z0-9]/.test(password)) score++;
        else feedback.push('Un car�cter especial');

        const levels = ['Muy d�bil', 'D�bil', 'Regular', 'Fuerte', 'Muy fuerte'];
        const colors = ['danger', 'danger', 'warning', 'info', 'success'];

        return {
            score: score,
            level: levels[score] || 'Muy d�bil',
            color: colors[score] || 'danger',
            isValid: score >= 2,
            message: score >= 2 ? `Fortaleza: ${levels[score]}` : `Requiere: ${feedback.join(', ')}`,
            feedback: feedback
        };
    }

    updatePasswordStrength(input, validation) {
        const strengthIndicator = $(input).siblings('.password-strength');
        
        if (strengthIndicator.length === 0) {
            $(input).after(`
                <div class="password-strength mt-2">
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" role="progressbar"></div>
                    </div>
                    <small class="strength-text"></small>
                </div>
            `);
        }

        const progressBar = $(input).siblings('.password-strength').find('.progress-bar');
        const strengthText = $(input).siblings('.password-strength').find('.strength-text');

        progressBar
            .removeClass('bg-danger bg-warning bg-info bg-success')
            .addClass(`bg-${validation.color}`)
            .css('width', `${(validation.score / 5) * 100}%`);

        strengthText
            .removeClass('text-danger text-warning text-info text-success')
            .addClass(`text-${validation.color}`)
            .text(validation.level);
    }

    setValidationState(input, isValid, message = '') {
        const $input = $(input);
        
        $input.removeClass('is-valid is-invalid');
        $input.siblings('.valid-feedback, .invalid-feedback').remove();

        if (isValid) {
            $input.addClass('is-valid');
            if (message) {
                $input.after(`<div class="valid-feedback">${message}</div>`);
            }
        } else {
            $input.addClass('is-invalid');
            if (message) {
                $input.after(`<div class="invalid-feedback">${message}</div>`);
            }
        }
    }

    // M�todos auxiliares
    async makeRequest(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }

        return await response.json();
    }

    showLoading(selector) {
        const $button = $(selector);
        $button.prop('disabled', true);
        $button.find('i').removeClass().addClass('fas fa-spinner fa-spin');
    }

    hideLoading(selector) {
        const $button = $(selector);
        $button.prop('disabled', false);
        $button.find('i').removeClass().addClass('fas fa-save');
    }

    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message
        });
    }

    refreshTable() {
        if (this.table) {
            this.table.ajax.reload(null, false);
        } else {
            location.reload();
        }
    }

    refreshStats() {
        // Actualizar estad�sticas sin recargar la p�gina
        fetch('usuarios.php?action=stats')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateStatsCards(data.stats);
                }
            })
            .catch(error => console.log('Error al actualizar estad�sticas:', error));
    }

    updateStatsCards(stats) {
        $('#stat-total').text(stats.total);
        $('#stat-activos').text(stats.activos);
        $('#stat-admins').text(stats.admins);
        $('#stat-recepcionistas').text(stats.recepcionistas);
    }

    generarContrase�a() {
        const caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        let contrase�a = '';
        
        for (let i = 0; i < 12; i++) {
            contrase�a += caracteres.charAt(Math.floor(Math.random() * caracteres.length));
        }
        
        $('#nueva_contrase�a').val(contrase�a);
        this.validatePassword(document.getElementById('nueva_contrase�a'));
    }
}

// Funciones globales para compatibilidad
function editarUsuario(id) {
    usuariosManager.obtenerUsuario(id).then(usuario => {
        if (usuario) {
            $('#editar_id').val(usuario.id);
            $('#editar_nombre').val(usuario.nombre);
            $('#editar_usuario').val(usuario.usuario);
            $('#editar_correo').val(usuario.correo);
            $('#editar_telefono').val(usuario.telefono);
            $('#editar_rol').val(usuario.rol);
            $('#editar_estado').val(usuario.estado);
            
            $('#modalEditarUsuario').modal('show');
        }
    });
}

function cambiarContrase�a(id) {
    usuariosManager.obtenerUsuario(id).then(usuario => {
        if (usuario) {
            $('#contrase�a_id').val(usuario.id);
            $('#contrase�a_usuario_nombre').text(usuario.nombre);
            
            $('#modalCambiarContrase�a').modal('show');
        }
    });
}

function eliminarUsuario(id, nombre) {
    usuariosManager.eliminarUsuario(id, nombre);
}

// Funci�n debounce para optimizar las b�squedas
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Inicializar cuando el documento est� listo
let usuariosManager;

// Inicializar cuando el documento est� listo
let usuariosManager;

$(document).ready(function() {
    usuariosManager = new UsuariosManager();
    
    // Configurar eventos adicionales
    setupAdditionalEvents();
    
    // Configurar theme toggle si existe
    setupThemeToggle();
    
    // Configurar shortcuts de teclado
    setupKeyboardShortcuts();
});

function setupAdditionalEvents() {
    // Exportar datos
    $('#exportarUsuarios').on('click', function() {
        exportarDatos();
    });
    
    // Importar usuarios
    $('#importarUsuarios').on('click', function() {
        $('#modalImportar').modal('show');
    });
    
    // Vista previa de avatar
    $('#foto_perfil').on('change', function() {
        previewAvatar(this);
    });
    
    // Copiar informaci�n del usuario
    $('.btn-copy').on('click', function() {
        copyToClipboard($(this).data('text'));
    });
    
    // Enviar email de bienvenida
    $('.btn-email').on('click', function() {
        const userId = $(this).data('user-id');
        enviarEmailBienvenida(userId);
    });
}

function setupThemeToggle() {
    const themeToggle = $('#themeToggle');
    if (themeToggle.length) {
        themeToggle.on('click', function() {
            toggleTheme();
        });
    }
}

function setupKeyboardShortcuts() {
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + N = Nuevo usuario
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            $('#modalCrearUsuario').modal('show');
        }
        
        // Escape = Cerrar modales
        if (e.key === 'Escape') {
            $('.modal.show').modal('hide');
        }
        
        // Ctrl/Cmd + F = Buscar
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            $('#busquedaGlobal').focus();
        }
    });
}

function exportarDatos() {
    const formato = $('#formatoExportacion').val() || 'excel';
    
    Swal.fire({
        title: 'Exportando datos...',
        text: 'Por favor espera mientras se genera el archivo',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Crear formulario invisible para descarga
    const form = $('<form>', {
        method: 'POST',
        action: 'exportar.php'
    });
    
    form.append($('<input>', {
        type: 'hidden',
        name: 'formato',
        value: formato
    }));
    
    form.append($('<input>', {
        type: 'hidden',
        name: 'csrf_token',
        value: usuariosManager.csrfToken
    }));
    
    $('body').append(form);
    form.submit();
    form.remove();
    
    setTimeout(() => {
        Swal.close();
    }, 2000);
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            $('#avatar-preview').attr('src', e.target.result).show();
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Mostrar notificaci�n toast
        showToast('Copiado al portapapeles', 'success');
    }).catch(function(err) {
        console.error('Error al copiar: ', err);
        showToast('Error al copiar', 'error');
    });
}

function showToast(message, type = 'info') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Crear contenedor de toasts si no existe
    if (!$('#toast-container').length) {
        $('body').append('<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
    }
    
    const $toast = $(toastHtml);
    $('#toast-container').append($toast);
    
    const toast = new bootstrap.Toast($toast[0]);
    toast.show();
    
    // Remover el toast despu�s de que se oculte
    $toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

function enviarEmailBienvenida(userId) {
    Swal.fire({
        title: '�Enviar email de bienvenida?',
        text: 'Se enviar� un correo con las credenciales de acceso',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'enviar_email_bienvenida');
            formData.append('user_id', userId);
            formData.append('csrf_token', usuariosManager.csrfToken);
            
            fetch('usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Email enviado correctamente', 'success');
                } else {
                    showToast('Error al enviar email: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error de conexi�n', 'error');
            });
        }
    });
}

function toggleTheme() {
    const currentTheme = localStorage.getItem('theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Actualizar icono del bot�n
    const icon = $('#themeToggle i');
    if (newTheme === 'dark') {
        icon.removeClass('fa-moon').addClass('fa-sun');
    } else {
        icon.removeClass('fa-sun').addClass('fa-moon');
    }
}

// Aplicar tema guardado al cargar la p�gina
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Actualizar icono si existe
    if (savedTheme === 'dark') {
        $('#themeToggle i').removeClass('fa-moon').addClass('fa-sun');
    }
})();

// Funciones adicionales para mejorar UX
function resetearContrase�a(userId) {
    Swal.fire({
        title: '�Resetear contrase�a?',
        text: 'Se generar� una nueva contrase�a temporal',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Resetear',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'resetear_contrase�a');
            formData.append('user_id', userId);
            formData.append('csrf_token', usuariosManager.csrfToken);
            
            fetch('usuarios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Contrase�a reseteada',
                        text: `Nueva contrase�a temporal: ${data.nueva_contrase�a}`,
                        icon: 'success',
                        confirmButtonText: 'Copiar contrase�a'
                    }).then(() => {
                        copyToClipboard(data.nueva_contrase�a);
                    });
                } else {
                    showToast('Error al resetear contrase�a: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error de conexi�n', 'error');
            });
        }
    });
}

function verDetallesUsuario(userId) {
    usuariosManager.obtenerUsuario(userId).then(usuario => {
        if (usuario) {
            const modalContent = `
                <div class="modal fade" id="modalDetallesUsuario" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-user me-2"></i>
                                    Detalles del Usuario
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-4 text-center mb-3">
                                        <img src="${obtenerAvatarUsuario(usuario)}" alt="Avatar" class="rounded-circle" width="120" height="120">
                                        <h5 class="mt-2">${usuario.nombre}</h5>
                                        <span class="badge bg-${usuario.rol === 'admin' ? 'primary' : 'secondary'}">${usuario.rol}</span>
                                    </div>
                                    <div class="col-md-8">
                                        <table class="table table-borderless">
                                            <tr>
                                                <td><strong>Usuario:</strong></td>
                                                <td>${usuario.usuario}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Correo:</strong></td>
                                                <td>${usuario.correo}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Tel�fono:</strong></td>
                                                <td>${usuario.telefono || 'No especificado'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Estado:</strong></td>
                                                <td><span class="badge bg-${usuario.estado === 'activo' ? 'success' : 'danger'}">${usuario.estado}</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>�ltimo acceso:</strong></td>
                                                <td>${usuario.ultimo_acceso || 'Nunca'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Registrado:</strong></td>
                                                <td>${usuario.creado_en}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                <button type="button" class="btn btn-primary" onclick="editarUsuario(${usuario.id})">
                                    <i class="fas fa-edit me-2"></i>Editar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior si existe
            $('#modalDetallesUsuario').remove();
            
            // Agregar nuevo modal
            $('body').append(modalContent);
            $('#modalDetallesUsuario').modal('show');
        }
    });
}

function obtenerAvatarUsuario(usuario) {
    if (usuario.foto) {
        return `../uploads/avatars/${usuario.foto}`;
    }
    
    // Generar avatar con iniciales
    const iniciales = usuario.nombre.substring(0, 2).toUpperCase();
    const colores = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997'];
    const color = colores[iniciales.charCodeAt(0) % colores.length];
    
    return `data:image/svg+xml;base64,${btoa(`
        <svg width="120" height="120" xmlns="http://www.w3.org/2000/svg">
            <circle cx="60" cy="60" r="60" fill="${color}"/>
            <text x="60" y="75" font-family="Arial" font-size="36" fill="white" text-anchor="middle">${iniciales}</text>
        </svg>
    `)}`;
}

// Configurar notificaciones en tiempo real (opcional)
function setupRealTimeNotifications() {
    // Verificar soporte para WebSockets o Server-Sent Events
    if (typeof EventSource !== "undefined") {
        const eventSource = new EventSource('notifications.php');
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            if (data.type === 'user_activity') {
                showToast(`Usuario ${data.username} ${data.action}`, 'info');
                usuariosManager.refreshStats();
            }
        };
        
        eventSource.onerror = function(error) {
            console.log('Error en notificaciones en tiempo real:', error);
        };
    }
}

// Inicializar notificaciones si est�n disponibles
$(document).ready(function() {
    // setupRealTimeNotifications(); // Descomentar si se implementan las notificaciones
});

// Configurar auto-logout por inactividad
let inactivityTimer;
const INACTIVITY_TIME = 30 * 60 * 1000; // 30 minutos

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        Swal.fire({
            title: 'Sesi�n inactiva',
            text: 'Tu sesi�n ha expirado por inactividad',
            icon: 'warning',
            allowOutsideClick: false,
            showConfirmButton: true,
            confirmButtonText: 'Iniciar sesi�n'
        }).then(() => {
            window.location.href = '../logout.php';
        });
    }, INACTIVITY_TIME);
}

// Eventos que resetean el timer de inactividad
$(document).on('mousemove keydown click scroll', resetInactivityTimer);

// Inicializar timer al cargar la p�gina
$(document).ready(resetInactivityTimer);