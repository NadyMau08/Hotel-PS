// Gestor de impresión térmica
class ThermalPrintManager {
    constructor() {
        this.printerType = 'thermal'; // 'thermal' o 'standard'
        this.paperWidth = '80mm'; // 58mm o 80mm
    }

    // Generar ticket térmico
    async generateThermalTicket(reservaId) {
        try {
            // Abrir ventana con ticket optimizado para térmica
            const ticketWindow = window.open(
                `generar_ticket_termico.php?id=${reservaId}&autoprint=1`,
                'ticket_termico',
                'width=300,height=600,scrollbars=yes'
            );

            // Opcional: cerrar ventana después de imprimir
            ticketWindow.onafterprint = function() {
                setTimeout(() => {
                    ticketWindow.close();
                }, 1000);
            };

            return true;
        } catch (error) {
            console.error('Error generando ticket térmico:', error);
            return false;
        }
    }

    // Detectar tipo de impresora disponible
    detectPrinterType() {
        // Detectar si hay impresoras térmicas disponibles
        if (navigator.userAgent.includes('ThermalPrint')) {
            return 'thermal';
        }
        
        // Por defecto usar impresión estándar
        return 'standard';
    }

    // Método principal para imprimir ticket
    async printTicket(reservaId, options = {}) {
        const printerType = options.printerType || this.detectPrinterType();
        
        if (printerType === 'thermal') {
            return this.generateThermalTicket(reservaId);
        } else {
            // Usar el PDF tradicional
            window.open(`generar_ticket.php?id=${reservaId}`, '_blank');
            return true;
        }
    }

    // Configurar tipo de impresora
    setPrinterType(type) {
        this.printerType = type;
        localStorage.setItem('printer_type', type);
    }

    // Obtener configuración guardada
    loadSettings() {
        const savedType = localStorage.getItem('printer_type');
        if (savedType) {
            this.printerType = savedType;
        }
    }
}

// Inicializar gestor de impresión
const printManager = new ThermalPrintManager();
printManager.loadSettings();

// Función para actualizar los botones de ticket
function updateTicketButtons() {
    // Actualizar todos los enlaces de ticket existentes
    document.addEventListener('click', function(e) {
        const ticketLink = e.target.closest('a[href*="generar_ticket.php"]');
        
        if (ticketLink) {
            e.preventDefault();
            
            // Extraer ID de la URL
            const url = new URL(ticketLink.href);
            const reservaId = url.searchParams.get('id');
            
            if (reservaId) {
                // Mostrar menú de opciones de impresión
                showPrintOptions(reservaId);
            }
        }
    });
}

// Mostrar opciones de impresión
function showPrintOptions(reservaId) {
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 20px; border-radius: 8px; max-width: 400px;">
            <h3>Opciones de Impresión</h3>
            <p>Selecciona el tipo de ticket para la reserva #${reservaId}:</p>
            
            <button onclick="printTicket(${reservaId}, 'thermal')" 
                    style="display: block; width: 100%; margin: 10px 0; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                🖨️ Ticket Térmico (58mm/80mm)
            </button>
            
            <button onclick="printTicket(${reservaId}, 'pdf')" 
                    style="display: block; width: 100%; margin: 10px 0; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                📄 Ticket PDF (A4)
            </button>
            
            <button onclick="closeModal()" 
                    style="display: block; width: 100%; margin: 10px 0; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                ❌ Cancelar
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Funciones del modal
    window.printTicket = function(id, type) {
        if (type === 'thermal') {
            printManager.generateThermalTicket(id);
        } else {
            window.open(`generar_ticket.php?id=${id}`, '_blank');
        }
        closeModal();
    };
    
    window.closeModal = function() {
        document.body.removeChild(modal);
        delete window.printTicket;
        delete window.closeModal;
    };
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    updateTicketButtons();
});

// Ejemplo de uso directo:
// printManager.printTicket(1, {printerType: 'thermal'});