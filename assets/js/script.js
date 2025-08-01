import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import resourceTimelinePlugin from '@fullcalendar/resource-timeline';
import interactionPlugin from '@fullcalendar/interaction';


// Constantes para estados de habitaciones
const ROOM_STATUS = {
    DISPONIBLE: 'disponible',
    OCUPADA: 'ocupada', 
    MANTENIMIENTO: 'mantenimiento',
    LIMPIEZA: 'limpieza',
    BLOQUEADA: 'bloqueada', 
};

// Variables globales
let calendarEl = null;
let dynamicContentEl = null;
let reservationModal = null;
let reservationSubmitBtn = null;
let roomAvailabilityWarning = null;
let calendarInstance = null;
let currentEditingReservationId = null;
let currentEditingGuestId = null;
let currentEditingRoomId = null;

// Mapeo de colores para estados de reservación
const statusColorMap = {
    'WALKING': '#FFA500', 
    'BLOQUEADA_OTRAS': '#FF0000', 
    'RESERVACION_PREVIA': '#FFD700', 
    'CANCELADA': '#000000', 
    'BLOQUEADA_PAGO': '#0000FF', 
    'PENDIENTE_PAGO': '#A52A2A', 
    'default': '#3498db' 
};

// ==================== FUNCIONES DE CONEXIÓN A LA API ====================

/**
 * Obtiene todas las habitaciones desde la API
 */
async function fetchRooms() {
    try {
        const response = await fetch('api/rooms.php?action=get_all');
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return await response.json();
    } catch (error) {
        console.error('Error fetching rooms:', error);
        showAlert('Error al cargar las habitaciones', 'error');
        return [];
    }
}

/**
 * Obtiene todas las reservaciones desde la API
 */
async function fetchReservations() {
    try {
        const response = await fetch('api/reservations.php?action=get_all');
        const text = await response.text();
        console.log('Respuesta cruda del servidor:', text);

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Respuesta inválida del servidor:', text);
            return [];
        }

        return result.data || []; // <- Esto es crucial
    } catch (error) {
        console.error('Error fetching reservations:', error);
        return [];
    }
}


/**
 * Obtiene todos los huéspedes desde la API
 */
async function fetchGuests() {
    try {
        const response = await fetch('api/guest.php?action=get_all');
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return await response.json();
    } catch (error) {
        console.error('Error fetching guests:', error);
        showAlert('Error al cargar los huéspedes', 'error');
        return [];
    }
}
async function getGuestById(guestId) {
    try {
        const response = await fetch(`api/guest.php?action=get&id=${guestId}`);
        if (!response.ok) throw new Error('No se pudo obtener el huésped');
        return await response.json();
    } catch (error) {
        console.error('Error al obtener el huésped:', error);
        return null;
    }
}

/**
 * Guarda una reservación en la base de datos
 */
async function saveReservation(reservationData) {
    try {
        const action = reservationData.id ? 'update' : 'add';
        const response = await fetch(`api/reservations.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(reservationData)
        });

        const result = await response.json();
        console.log('Respuesta del servidor:', result);

        if (result.success) {
            return {
                success: true,
                insert_id: result.insert_id || reservationData.id, // usar insert_id si es nuevo, o el mismo id si es edición
                reservation: reservationData
            };
        } else {
            return {
                success: false,
                message: result.message,
                error: result.error
            };
        }

    } catch (error) {
        console.error('Error al conectar con el servidor:', error);
        return {
            success: false,
            message: 'Error de conexión al guardar reserva',
            error: error.message
        };
    }
}



/**
 * Elimina una reservación
 */
async function deleteReservation(reservationId) {
    try {
        const response = await fetch(`api/reservations.php?action=delete&id=${reservationId}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return await response.json();
    } catch (error) {
        console.error('Error deleting reservation:', error);
        return { success: false, error: error.message };
    }
}

/**
 * Guarda una habitación en la base de datos
 */
async function saveRoom(roomData) {
    try {
        const url = roomData.id 
            ? 'api/rooms.php?action=update' 
            : 'api/rooms.php?action=add';
            
        const method = roomData.id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(roomData)
        });
        
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return await response.json();
    } catch (error) {
        console.error('Error saving room:', error);
        return { success: false, error: error.message };
    }
}

/**
 * Guarda un huésped en la base de datos
 */
async function saveGuest(guestData) {
    try {
        const url = guestData.id 
            ? 'api/guest.php?action=update' 
            : 'api/guest.php?action=add';
            
        const method = guestData.id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(guestData)
        });
        
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
          return await response.json();
    } catch (error) {
        console.error('Error saving guest:', error);
        return { success: false, error: error.message };
    }
}

// ==================== FUNCIONES DEL CALENDARIO ====================

/**
 * Renderiza el calendario con datos de la base de datos
 */
async function renderCalendar() {
    try {
      const [rooms, reservations] = await Promise.all([
  fetchRooms(),
  fetchReservations()
]);
window.TODAS_LAS_HABITACIONES = rooms;

        
        if (calendarInstance) {
            calendarInstance.destroy();
        }

        calendarInstance = new Calendar(calendarEl, {
            plugins: [resourceTimelinePlugin, dayGridPlugin, interactionPlugin],
            initialView: 'resourceTimelineMonth',
            schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'resourceTimelineDay,resourceTimelineWeek,resourceTimelineMonth,dayGridMonth'
            },
         slotLabelFormat: [
  {
    weekday: 'short', // 'long' si quieres "lunes", "martes", etc.
    day: 'numeric'
  }
],
slotLabelInterval: { days: 1 },
slotDuration: '24:00:00',
slotLabelDidMount: function(info) {
  // Esto te ayuda a controlar estilos directamente si hace falta
  info.el.style.whiteSpace = "normal";
},
            locale: 'es',
            resourceAreaHeaderContent: 'Habitaciones',
            resources: rooms.map(room => ({
                id: room.id,
                title: `${room.type} ${room.number || ''}`.trim(),
                extendedProps: {
                    inapam: room.inapam
                }
            })),
            events: reservations.map(res => ({
                id: res.id,
                title: res.title,
                start: res.start,
               end: res.end ? res.end.split('T')[0] + 'T16:00:00' : undefined,
                color: res.color,
                backgroundColor: '',
                resourceId: res.resourceId,
                extendedProps: res.extendedProps, 
                classNames: ['custom-event'],
                display: 'block',// ✅ Así ya incluye todo automáticamente
                initialView: 'resourceTimelineWeek',
                

            })),
            validRange: function(nowDate) {
  if (ROL_USUARIO !== 'admin') {
    const start = new Date();
    start.setDate(1); // Primer día del mes actual

    const end = new Date();
    end.setMonth(end.getMonth() + 3); // 2 meses adelante (actual + 2)
    end.setDate(0); // Último día del segundo mes

    return {
      start: start.toISOString().split('T')[0],
      end: end.toISOString().split('T')[0]
    };
  }

  // Si es admin, sin restricciones
  return null;
},


            selectable: true,
            dateClick: handleDateClick,
            eventClick: handleEventClick,
            eventDidMount: function(info) {
                customizeEventDisplay(info);
            },
            resourceLabelDidMount: function(info) {
                addInapamIndicator(info);
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día',
                list: 'Lista',
                resourceTimelineDay: 'Día',
                resourceTimelineWeek: 'Semana',
                resourceTimelineMonth: 'Mes (Hab.)',
                dayGridMonth: 'Mes (Grid)'
            }
        });
        
        calendarInstance.render();
    } catch (error) {
        console.error('Error rendering calendar:', error);
        showAlert('Error al cargar el calendario', 'error');
    }

    
  }



/**
 * Personaliza la visualización de eventos en el calendario
 */
function customizeEventDisplay(info) {
    const titleEl = info.el.querySelector('.fc-event-title');
    if (titleEl) {
        titleEl.style.overflow = 'visible';
        titleEl.style.whiteSpace = 'normal';
    }
    
    if (!info.el.querySelector('.fc-event-title-custom')) {
        const contentEl = document.createElement('div');
        contentEl.classList.add('fc-event-title-custom');
        contentEl.textContent = info.event.title;
        
        const guestCount = info.event.extendedProps?.checkinGuests?.length;
        if (guestCount > 1) {
            const guestIcon = document.createElement('span');
            guestIcon.innerHTML = ` <i class="fas fa-users" title="${guestCount} huéspedes"></i>`;
            guestIcon.style.marginLeft = '5px';
            guestIcon.style.opacity = '0.8';
            contentEl.appendChild(guestIcon);
        }
        
        const eventContentContainer = info.el.querySelector('.fc-event-main-frame') || info.el;
        if (eventContentContainer.firstChild) {
            eventContentContainer.insertBefore(contentEl, eventContentContainer.firstChild);
        } else {
            eventContentContainer.appendChild(contentEl);
        }
    }
}

/**
 * Añade indicador INAPAM a las habitaciones
 */
function addInapamIndicator(info) {
    const resource = info.resource;
    const roomData = info.resource.extendedProps;

    if (roomData && roomData.inapam) {
        const labelEl = info.el.querySelector('.fc-datagrid-cell-main');
        if (labelEl) {
            const inapamIndicator = document.createElement('span');
            inapamIndicator.innerHTML = ' <i class="fas fa-check-circle inapam-indicator" title="Acepta INAPAM"></i>';
            inapamIndicator.style.color = '#28a745';
            inapamIndicator.style.marginLeft = '5px';
            inapamIndicator.style.cursor = 'help';
            labelEl.appendChild(inapamIndicator);
        }
    }
}

// ==================== MANEJO DE RESERVACIONES ====================

/**
 * Maneja el clic en una fecha del calendario
 */
async function handleDateClick(info) {
    console.log('handleDateClick called', info);

    if (info.resource) {
        const roomId = info.resource.id;
        const room = await getRoomById(roomId);
        console.log('Habitación obtenida:', room);

        if (!room) {
            showAlert('No se encontró la habitación', 'error');
            return;
        }

        if (room.status !== 'disponible') {
            const statusText = getRoomStatusText(room.status);
            showAlert(`La habitación "${info.resource.title}" no está disponible (Estado: ${statusText}).`, 'warning');
            return;
        }
    }

    console.log('Abriendo modal...');
    openReservationModal({
        date: info.date,
        dateStr: info.dateStr,
        resource: info.resource
    });
}

window.abrirBuscadorAnticipos = function() {
  // Limpiar el campo de búsqueda
  const searchInput = document.getElementById('searchAnticipo');
  const resultadosDiv = document.getElementById('resultadosAnticipos');

  if (searchInput) searchInput.value = '';
  if (resultadosDiv) resultadosDiv.innerHTML = '<p class="text-muted">Escribe para buscar...</p>';

  // Mostrar el modal usando Bootstrap 5
  const modalElement = document.getElementById('modalBuscarAnticipos');
  if (modalElement) {
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
  } else {
    console.error('No se encontró el modalBuscarAnticipos');
  }
};


window.buscarAnticipos = async function() {
  const query = document.getElementById('searchAnticipo').value;
  const resultados = document.getElementById('resultadosAnticipos');

  if (!query) {
    resultados.innerHTML = '<p class="text-muted">Escribe para buscar...</p>';
    return;
  }

  try {
    const res = await fetch(`api/anticipos.php?action=search&query=${encodeURIComponent(query)}`);
    const json = await res.json();

    if (json.success && Array.isArray(json.data)) {
      resultados.innerHTML = json.data.map(a => `
        <div class="list-group-item list-group-item-action" onclick="seleccionarAnticipo(${JSON.stringify(a).replace(/"/g, '&quot;')})">
          <strong>Folio:</strong> ${a.ticket} - ${a.guest} - $${a.anticipo}
        </div>
      `).join('');
    } else {
      resultados.innerHTML = '<p class="text-danger">No se encontraron anticipos.</p>';
    }
  } catch (err) {
    console.error(err);
    resultados.innerHTML = '<p class="text-danger">Error al buscar anticipos.</p>';
  }
}

// REGISTRARLA GLOBALMENTE:
window.seleccionarAnticipo = function(anticipo) {
  const container = document.getElementById('pagosHotelContainer');
  const template = document.getElementById('pagoHotelTemplate').innerHTML;
  const index = container.children.length;

  let newHTML = template.replace(/{{index}}/g, index);
  newHTML = newHTML.replace('value=""', `value="${anticipo.anticipo}"`);
  newHTML = newHTML.replace('<option value="">Seleccionar</option>', `<option value="${anticipo.metodo_pago}" selected>${anticipo.metodo_pago}</option>`);

  const div = document.createElement('div');
  div.innerHTML = newHTML;
  container.appendChild(div);

  div.querySelector('.remove-payment-btn').onclick = () => {
    div.remove();
    updatePaymentSummary();
  };

  updatePaymentSummary();

  // Cerrar el modal
  const modal = bootstrap.Modal.getInstance(document.getElementById('modalBuscarAnticipos'));
  modal.hide();
};



async function populateReservationModalDropdowns() {
    try {
        // Llenar select de huéspedes
        const guests = await fetchGuests();
        const guestSelect = document.getElementById('reservationGuestSelect');
        guestSelect.innerHTML = '<option value="">Seleccionar huésped</option>';
        guests.forEach(g => {
            const option = document.createElement('option');
            option.value = g.id;
            option.textContent = g.nombre;
            guestSelect.appendChild(option);
        });

        // Llenar select de habitaciones
        const rooms = await fetchRooms();
        const roomSelect = document.getElementById('reservationRoomSelect');
        roomSelect.innerHTML = '<option value="">Seleccionar habitación</option>';
        rooms.forEach(r => {
            const option = document.createElement('option');
            option.value = r.id;
            option.textContent = `${r.type} ${r.number}`;
            roomSelect.appendChild(option);
        });

    } catch (error) {
        console.error('Error llenando los select del modal:', error);
        showAlert('Error al cargar datos del formulario de reserva', 'error');
    }
}

/**
 * Maneja el clic en un evento del calendario
 */
function handleEventClick(info) {
    info.jsEvent.preventDefault();

    const event = info.event;

    const eventData = {
        ...event,
        id: event.id,
        title: event.title,
        startStr: event.start ? event.start.toISOString().split('T')[0] : '',
        endStr: event.end ? event.end.toISOString().split('T')[0] : '',
        resourceId: event.getResources?.()[0]?.id || event.getResourceId?.() || event.extendedProps.resourceId,
        extendedProps: event.extendedProps,
        backgroundColor: event.backgroundColor
    };

    openReservationModal(eventData);
}

/**
 * Abre el modal de reservación
 */
async function openReservationModal(info = {}) {
    const reservationForm = document.getElementById('reservationForm');
    if (!reservationForm) return;
    reservationForm.reset();

    switchTab('tab-reserva');
    await populateReservationModalDropdowns();

    // Limpiar contenedores
    document.getElementById('pagosHotelContainer')?.replaceChildren();
    document.getElementById('pagosExtraContainer')?.replaceChildren();
    document.getElementById('checkinGuestsContainer')?.replaceChildren();
    document.querySelectorAll('#checkinItemsContainer .additional-item-row').forEach(row => row.remove());

    currentEditingReservationId = null;
    if (roomAvailabilityWarning) roomAvailabilityWarning.style.display = 'none';

    document.getElementById('reservationRate')?.classList.remove('readonly-input');
    if (document.getElementById('reservationRate')) document.getElementById('reservationRate').readOnly = false;

    const safeSetValue = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.value = value ?? '';
    };

    const props = info.extendedProps || {};

    if (info.id) {
        currentEditingReservationId = info.id;
        safeSetValue('reservationId', info.id);
        document.getElementById('reservationModalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Reserva';
        document.getElementById('reservationSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';

        // TAB 1 - Reserva
        safeSetValue('reservationRoomSelect', info.resourceId || '');
        safeSetValue('checkInDate', info.startStr?.split('T')[0]);
        safeSetValue('checkOutDate', info.endStr?.split('T')[0]);
        safeSetValue('reservationStatus', props.status || 'RESERVACION_PREVIA');
        safeSetValue('reservationRate', props.rate || '');
        safeSetValue('reservationIVA', props.iva !== undefined ? props.iva : 16);
        safeSetValue('reservationISH', props.ish !== undefined ? props.ish : 3);

        // TAB 2 - Cliente
        safeSetValue('reservationGuestSelect', props.guestId || '');
        if (props.guestId) {
            await populateManualGuestFields(props.guestId);
        } else {
            safeSetValue('guestNameManual', props.guestNameManual || '');
        }

        // TAB 3 - Pagos
        const anticipo = props.anticipo || {};
        safeSetValue('paymentAnticipoTicket', anticipo.ticket || '');
        safeSetValue('paymentAnticipoMonto', anticipo.monto || '');
        safeSetValue('paymentAnticipoMetodo', anticipo.metodo || '');
        
        populateExtraPayments(props.pagosExtra || []);
        populateHotelPayments(props.pagosHotel || []);

        // TAB 4 - Verificación
        const verification = props.verification || {};
        safeSetValue('verificationDateTime', verification.dateTime || '');
        safeSetValue('verificationWhatsApp', verification.whatsAppVerified || 'No');
        safeSetValue('verificationSenderName', verification.senderName || '');

        // TAB 5 - Check-in
        populateCheckinGuests(props.checkinGuests || []);
        populateCheckinItems(props.checkinItems || {});
       safeSetValue('receptionistName', typeof nombreUsuario !== 'undefined' ? nombreUsuario : '');

        // INAPAM
        const inapamCheck = document.getElementById('inapamDiscount');
        if (inapamCheck) inapamCheck.checked = props.inapamDiscount || false;
        safeSetValue('inapamCredential', props.inapamCredential || '');
        safeSetValue('inapamDiscountValue', props.inapamDiscountValue || '');
        safeSetValue('inapamDiscountType', props.inapamDiscountType || 'porcentaje'); // 🧡 Aquí!

        // Notas y color
        safeSetValue('reservationNotes', props.notes || '');
        safeSetValue('reservationColor', info.backgroundColor || statusColorMap[props.status] || statusColorMap['default']);

    } else {
        document.getElementById('reservationModalTitle').innerHTML = '<i class="fas fa-calendar-plus"></i> Nueva Reserva';
        document.getElementById('reservationSubmitBtn').innerHTML = '<i class="fas fa-save"></i> Crear Reserva';
        safeSetValue('reservationId', '');

        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];

        const checkInDate = info.dateStr ? info.dateStr.split('T')[0] : todayStr;
        const checkOutDate = tomorrowStr;

        safeSetValue('checkInDate', checkInDate);
        const checkOutInput = document.getElementById('checkOutDate');
        if (checkOutInput) {
            checkOutInput.value = checkOutDate;
        }

        safeSetValue('reservationRoomSelect', info.resource?.id || '');
        safeSetValue('reservationStatus', 'RESERVACION_PREVIA');
       safeSetValue('receptionistName', typeof nombreUsuario !== 'undefined' ? nombreUsuario : '');


        populateCheckinGuests([]);
        populateCheckinItems({});
    }

    // 💡 Forzar cálculos y actualizaciones
    updateRateFromSelectedRoom();
    updateNightsAndTotal();
    toggleInapamDetails();
    updateStatusColor();
    updateGuestAddButtonState();
    calculateCheckinItemsTotal();
    updateSubmitButtonState();
    setupReservationModalEventListeners();

    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'block';
        tab.style.visibility = 'hidden';
    });
await cargarConfiguracionEnModalReserva();

    const modal = document.getElementById('reservationModal');
    if (modal) modal.style.display = 'block';

    setTimeout(() => {
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = '';
            tab.style.visibility = '';
        });
    }, 100);
}

async function cargarConfiguracionEnModalReserva() {
  try {
    const res = await fetch('api/config.php?action=get');
    const data = await res.json();

    if (data.success) {
      const ivaInput = document.getElementById('reservationIVA');
      const ishInput = document.getElementById('reservationISH');

      if (ivaInput) ivaInput.value = data.iva ?? 0;
      if (ishInput) ishInput.value = data.ish ?? 0;

      const isAdmin = (ROL_USUARIO === 'admin'); // Cambia esto si tienes una forma real de saberlo

      ivaInput?.toggleAttribute('readonly', !isAdmin);
      ishInput?.toggleAttribute('readonly', !isAdmin);
    }
  } catch (err) {
    console.error("❌ Error al cargar configuración:", err);
  }
}


/**
 * Configura los event listeners del modal de reservación
 */
function setupReservationModalEventListeners() {
    const checkInDateInput = document.getElementById('checkInDate');
    const checkOutDateInput = document.getElementById('checkOutDate');
    const reservationRoomSelect = document.getElementById('reservationRoomSelect');
    const reservationRateInput = document.getElementById('reservationRate');
    const reservationIVAInput = document.getElementById('reservationIVA');
    const reservationISHInput = document.getElementById('reservationISH');
    const inapamCheckbox = document.getElementById('inapamDiscount');
    const inapamDiscountValueInput = document.getElementById('inapamDiscountValue');

    const reservationGuestSelect = document.getElementById('reservationGuestSelect');
    const reservationStatusSelect = document.getElementById('reservationStatus');
    const addCheckinGuestBtn = document.getElementById('addCheckinGuestBtn');
    const addCheckinItemBtn = document.getElementById('addCheckinItemBtn');
    const closeReservationModalBtn = document.getElementById('closeReservationModalBtn');
    const cancelReservationBtn = document.getElementById('cancelReservationBtn');
    const reservationForm = document.getElementById('reservationForm');
    const addGuestFromReservationBtnInline = document.getElementById('addGuestFromReservationBtnInline');
      
    // Configurar listeners
    checkInDateInput.onchange = () => {
        updateNightsAndTotal();
    };
    
    reservationGuestSelect.onchange = (e) => {
        const guestId = e.target.value;
    
        if (guestId) {
            populateManualGuestFields(guestId);
        } else {
            resetManualGuestFields();
        }
    };
    function resetManualGuestFields() {
        document.getElementById('guestNameManual').value = '';
        document.getElementById('guestNationalityManual').value = '';
        document.getElementById('guestPhoneManual').value = '';
        document.getElementById('guestAddressManual').value = '';
        document.getElementById('guestRFCManual').value = '';
        document.getElementById('guestEmailManual').value = '';
    }
    
    checkOutDateInput.onchange = updateNightsAndTotal;
    reservationRoomSelect.onchange = () => {
        updateRateFromSelectedRoom();
        updateGuestAddButtonState();
        updateSubmitButtonState();
    };
    reservationRateInput.oninput = calculateReservationTotal;
    inapamCheckbox.onchange = toggleInapamDetails;
    inapamDiscountValueInput.oninput = calculateReservationTotal;
    reservationGuestSelect.onchange = (e) => populateManualGuestFields(e.target.value);
    reservationStatusSelect.onchange = updateStatusColor;addCheckinGuestBtn.onclick = () => addCheckinGuestRow();
    addCheckinItemBtn.onclick = () => addCheckinItemRow();
    closeReservationModalBtn.onclick = closeReservationModal;
    cancelReservationBtn.onclick = closeReservationModal;
    reservationForm.onsubmit = handleReservationSubmit;
    addGuestFromReservationBtnInline.onclick = () => openGuestModal();
    
    // Listener para cerrar al hacer clic fuera
    window.onclick = function(event) {
        if (event.target == document.getElementById('reservationModal')) {
            closeReservationModal();
        }
    };
}

/**
 * Cierra el modal de reservación
 */
function closeReservationModal() {
    const modal = document.getElementById('reservationModal');
    if (modal) {
        modal.style.display = 'none';
        window.onclick = null;
        
        // Limpiar todos los event listeners
        const inputs = [
            'checkInDate', 'checkOutDate', 'reservationRoomSelect', 
            'reservationRate', 'reservationIVA', 'reservationISH',
            'inapamDiscount', 'inapamDiscountValue', 'reservationGuestSelect',
            'reservationStatus'
        ];
        
        inputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.onchange = null;
        });
        
        document.getElementById('reservationForm').onsubmit = null;
        document.getElementById('closeReservationModalBtn').onclick = null;
        document.getElementById('cancelReservationBtn').onclick = null;
        document.getElementById('addGuestFromReservationBtnInline').onclick = null;
        
        const paymentInputs = document.querySelectorAll('.payment-amount-input');
        paymentInputs.forEach(input => input.oninput = null);
        
        const removePaymentBtns = document.querySelectorAll('.remove-payment-btn');
        removePaymentBtns.forEach(btn => btn.onclick = null);
        
        const checkinToggles = document.querySelectorAll('.checkin-item-toggle');
        checkinToggles.forEach(el => el.onchange = null);
        
        const removeGuestBtns = document.querySelectorAll('.remove-guest-btn');
        removeGuestBtns.forEach(el => el.onclick = null);
        
        const removeItemBtns = document.querySelectorAll('.remove-item-btn');
        removeItemBtns.forEach(el => el.onclick = null);
        
        const itemNameInputs = document.querySelectorAll('.additional-item-row input[name*="[name]"]');
        itemNameInputs.forEach(el => el.oninput = null);
        
        const itemPriceInputs = document.querySelectorAll('.checkin-item-price-input');
        itemPriceInputs.forEach(el => el.oninput = null);
    }
}

// ==================== FUNCIONES AUXILIARES DE RESERVACIONES ====================

/**
 * Obtiene los datos de una habitación por ID
 */
async function getRoomById(roomId) {
    try {
        const response = await fetch(`api/rooms.php?action=get&id=${roomId}`);
        if (!response.ok) throw new Error('Error en la respuesta del servidor');
        return await response.json();
    } catch (error) {
        console.error('Error getting room:', error);
        return null;
    }
}

/**
 * Valida la disponibilidad de una habitación
 */
async function validateRoomAvailability(roomId) {
    const room = await getRoomById(roomId);
    if (!room) {
        console.warn("validateRoomAvailability: Room not found for ID:", roomId);
        return false;
    }
    return room.status === ROOM_STATUS.DISPONIBLE;
}

/**
 * Actualiza el estado del botón de envío
 */
async function updateSubmitButtonState() {
    if (!reservationModal || !reservationSubmitBtn || !reservationRoomSelect || !roomAvailabilityWarning) return;

    const selectedRoomId = reservationRoomSelect.value;
    if (!selectedRoomId) {
        reservationSubmitBtn.disabled = true;
        roomAvailabilityWarning.style.display = 'none';
        return;
    }

    const isAvailable = await validateRoomAvailability(selectedRoomId);
    reservationSubmitBtn.disabled = !isAvailable;
    roomAvailabilityWarning.style.display = isAvailable ? 'none' : 'block';
}

/**
 * Calcula el número de noches entre dos fechas
 */
function calculateNights(startDate, endDate) {
    if (!startDate || !endDate) return 0;
    const start = new Date(startDate + 'T00:00:00');
    const end = new Date(endDate + 'T00:00:00');
    if (start >= end) return 0;
    const diffTime = Math.abs(end - start);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Calcula el total de la reservación
 */
function calculateReservationTotal() {
    const nights = parseInt(document.getElementById('reservationNights').value) || 0;
    const rate = parseFloat(document.getElementById('reservationRate').value) || 0;

    const inapamCheckbox = document.getElementById('inapamDiscount');
    const inapamDiscountTypeEl = document.getElementById('inapamDiscountType');
    const inapamDiscountValueEl = document.getElementById('inapamDiscountValue');

    const applyInapamDiscount = inapamCheckbox ? inapamCheckbox.checked : false;
    const inapamDiscountType = inapamDiscountTypeEl ? inapamDiscountTypeEl.value : 'porcentaje';
    const inapamDiscountValue = inapamDiscountValueEl ? parseFloat(inapamDiscountValueEl.value) || 0 : 0;

    if (nights <= 0 || rate <= 0) {
        document.getElementById('reservationTotal').textContent = '$0.00';
        updatePaymentSummary();
        return 0;
    }

    const baseRateTotal = nights * rate;
    let discountedBaseRate = baseRateTotal;

    if (applyInapamDiscount && inapamDiscountValue > 0) {
        if (inapamDiscountType === 'porcentaje') {
            discountedBaseRate -= baseRateTotal * (inapamDiscountValue / 100);
        } else if (inapamDiscountType === 'monto') {
            discountedBaseRate -= inapamDiscountValue;
        }
        discountedBaseRate = Math.max(0, discountedBaseRate);
    }

    const total = discountedBaseRate; // ❌ No sumamos IVA ni ISH

    // 👇 DEBUG EN CONSOLA
    console.log('DEBUG RESERVA ↓↓↓↓↓');
    console.log('Noches:', nights);
    console.log('Tarifa por noche:', rate);
    console.log('Subtotal (antes de descuento):', baseRateTotal);
    console.log('Aplicar INAPAM:', applyInapamDiscount);
    console.log('Tipo descuento:', inapamDiscountType);
    console.log('Valor descuento:', inapamDiscountValue);
    console.log('Subtotal con descuento:', discountedBaseRate);
    console.log('Total final:', total);

    document.getElementById('reservationTotal').textContent = `$${total.toFixed(2)}`;
    updatePaymentSummary();
    return total;
}



/**
 * Actualiza el resumen de pagos
 */
function updatePaymentSummary() {
    const reservationTotalText = document.getElementById('reservationTotal').textContent.replace(/[^0-9.]/g, '');
    const totalReservationCost = parseFloat(reservationTotalText) || 0;

    let totalPaid = 0;

    // Sumar anticipo (ticket)
    const anticipoAmount = parseFloat(document.getElementById('paymentAnticipoMonto').value) || 0;
    totalPaid += anticipoAmount;

    // Sumar pagos en hotel
    const hotelPayments = document.querySelectorAll('#pagosHotelContainer .payment-amount-input');
    hotelPayments.forEach(input => {
        totalPaid += parseFloat(input.value) || 0;
    });

    // Sumar pagos extra (a distancia)
    const extraPayments = document.querySelectorAll('#pagosExtraContainer .payment-amount-input');
    extraPayments.forEach(input => {
        totalPaid += parseFloat(input.value) || 0;
    });

    // Calcular saldo
    const balanceDue = totalReservationCost - totalPaid;

    // Mostrar resumen
    document.getElementById('reservationTotalDisplay').textContent = `$${totalReservationCost.toFixed(2)}`;
    document.getElementById('totalPaidDisplay').textContent = `$${totalPaid.toFixed(2)}`;
    document.getElementById('balanceDueDisplay').textContent = `$${balanceDue.toFixed(2)}`;
}

// Agregar Pago en Hotel
// FUNCIONES PARA PAGOS HOTEL
window.agregarPagoHotel = function () {
    const container = document.getElementById('pagosHotelContainer');
    const template = document.getElementById('pagoHotelTemplate').innerHTML;
    const index = container.children.length;

    const newPaymentHTML = template.replace(/{{index}}/g, index);
    const div = document.createElement('div');
    div.innerHTML = newPaymentHTML;
    container.appendChild(div);
    div.querySelector('.remove-payment-btn').onclick = () => {
        div.remove();
        updatePaymentSummary();
    };

    // Actualizar resumen
    updatePaymentSummary();
};

// FUNCIONES PARA PAGOS EXTRA
window.agregarPagoExtra = function () {
    const container = document.getElementById('pagosExtraContainer');
    const template = document.getElementById('pagoExtraTemplate').innerHTML;
    const index = container.children.length;

    const newPaymentHTML = template.replace(/{{index}}/g, index);
    const div = document.createElement('div');
    div.innerHTML = newPaymentHTML;
    container.appendChild(div);

    div.querySelector('.remove-payment-btn').onclick = () => {
        div.remove();
        updatePaymentSummary();
    };

    // Actualizar resumen
    updatePaymentSummary();
};


// FUNCION PARA SELECCIONAR ANTICIPO DESDE EL BUSCADOR
window.seleccionarAnticipo = function (anticipo) {
    document.getElementById('paymentAnticipoTicket').value = anticipo.ticket || '';
    document.getElementById('paymentAnticipoMonto').value = anticipo.anticipo || '';
    document.getElementById('paymentAnticipoMetodo').value = anticipo.metodo_pago || '';

    updatePaymentSummary();
};

// FUNCION PARA ACTUALIZAR RESUMEN
window.updatePaymentSummary = function () {
    let totalPagado = 0;

    // Anticipo (solo uno)
    const anticipoMonto = parseFloat(document.getElementById('paymentAnticipoMonto').value) || 0;
    totalPagado += anticipoMonto;

    // Pagos en Hotel
    const pagosHotel = document.querySelectorAll('#pagosHotelContainer .payment-amount-input');
    pagosHotel.forEach(input => {
        const val = parseFloat(input.value) || 0;
        totalPagado += val;
    });

    // Pagos Extra a Distancia
    const pagosExtra = document.querySelectorAll('#pagosExtraContainer .payment-amount-input');
    pagosExtra.forEach(input => {
        const val = parseFloat(input.value) || 0;
        totalPagado += val;
    });

    // Actualizar visual
    document.getElementById('totalPaidDisplay').innerText = `$${totalPagado.toFixed(2)}`;

    // Calcular saldo
    const totalReserva = parseFloat(document.getElementById('reservationTotalDisplay').dataset.total || 0);
    const saldo = totalReserva - totalPagado;
    document.getElementById('balanceDueDisplay').innerText = `$${saldo.toFixed(2)}`;
};

// FUNCION PARA ESTABLECER EL TOTAL DE RESERVA DESDE BACKEND O MODAL
window.setReservationTotal = function (total) {
    document.getElementById('reservationTotalDisplay').innerText = `$${parseFloat(total).toFixed(2)}`;
    document.getElementById('reservationTotalDisplay').dataset.total = parseFloat(total).toFixed(2);
    updatePaymentSummary();
};

async function updateRateFromSelectedRoom() {
    const selectedRoomId = document.getElementById('reservationRoomSelect').value;
    const room = await getRoomById(selectedRoomId);
    
    if (room) {
        document.getElementById('reservationRate').value = room.price;
        document.getElementById('reservationRate').readOnly = false;
        document.getElementById('reservationRate').classList.remove('readonly-input');
    } else {
        document.getElementById('reservationRate').value = '';
        document.getElementById('reservationRate').readOnly = false;
        document.getElementById('reservationRate').classList.remove('readonly-input');
    }
    calculateReservationTotal();
}

/**
 * Actualiza el número de noches y el total
 */
function updateNightsAndTotal() {
    const nights = calculateNights(
        document.getElementById('checkInDate').value,
        document.getElementById('checkOutDate').value
    );
    document.getElementById('reservationNights').value = nights;
    calculateReservationTotal();
}

/**
 * Muestra/oculta los detalles de INAPAM
 */
function toggleInapamDetails() {
    const inapamDetailsDiv = document.getElementById('inapamDetails');
    const inapamCredentialInput = document.getElementById('inapamCredential');
    const inapamDiscountValueInput = document.getElementById('inapamDiscountValue');
    const isChecked = document.getElementById('inapamDiscount').checked;
    
    inapamDetailsDiv.style.display = isChecked ? 'block' : 'none';
    inapamCredentialInput.required = isChecked;
    inapamDiscountValueInput.required = isChecked;
    
    if (!isChecked) {
        inapamCredentialInput.value = '';
        inapamDiscountValueInput.value = '';
    }
    calculateReservationTotal();
}

/**
 * Actualiza el color basado en el estado
 */
function updateStatusColor() {
    const selectedStatus = document.getElementById('reservationStatus').value;
    const color = statusColorMap[selectedStatus] || statusColorMap['default'];
    document.getElementById('reservationColor').value = color;
}

// ==================== MANEJO DE PAGOS ====================
/**
 * Llena los pagos extra
 */
function populateExtraPayments(payments = []) {
    document.getElementById('pagosExtraContainer').innerHTML = '';
    payments.forEach(payment => {
        addExtraPaymentRow(payment);
    });
    updatePaymentSummary();
}

function addExtraPaymentRow(payment = {}) {
    const container = document.getElementById('pagosExtraContainer');
    const template = document.getElementById('pagoExtraTemplate')?.innerHTML;
    if (!container || !template) {
        console.error('Elementos necesarios no encontrados: botón, template o contenedor.');
        return;
    }

    const index = container.children.length;
    let newHTML = template.replace(/{{index}}/g, index);

    const div = document.createElement('div');
    div.innerHTML = newHTML;
    container.appendChild(div);

    // Llenar valores si hay datos
    if (payment) {
        const montoInput = div.querySelector('input[name*="[monto]"]');
        const metodoSelect = div.querySelector('select[name*="[metodo]"]');
        const claveInput = div.querySelector('input[name*="[clave]"]');
        const autorizacionInput = div.querySelector('input[name*="[autorizacion]"]');
        const fechaInput = div.querySelector('input[name*="[fecha]"]');

        if (montoInput) montoInput.value = payment.monto || '';
        if (metodoSelect) metodoSelect.value = payment.metodo || '';
        if (claveInput) claveInput.value = payment.clave || '';
        if (autorizacionInput) autorizacionInput.value = payment.autorizacion || '';
        if (fechaInput) fechaInput.value = payment.fecha || '';
    }

    // Eliminar fila
    div.querySelector('.remove-payment-btn').onclick = () => {
        div.remove();
        updatePaymentSummary();
    };

    updatePaymentSummary();
}
// Hacer la función global
window.addHotelPaymentRow = function(payment = {}) {
  const container = document.getElementById("pagosHotelContainer");
  const template = document.getElementById("pagoHotelTemplate")?.innerHTML;

  if (!container || !template) {
    console.error('Elementos necesarios no encontrados: botón, template o contenedor.');
    return;
  }

  const index = container.children.length;
  let newHTML = template.replace(/{{index}}/g, index);

  const div = document.createElement('div');
  div.innerHTML = newHTML;
  container.appendChild(div);

  // Llenar valores si hay datos
  const montoInput = div.querySelector('input[name*="[monto]"]');
  const metodoSelect = div.querySelector('select[name*="[metodo]"]');
  const fechaInput = div.querySelector('input[name*="[fecha]"]');

  if (payment) {
    if (montoInput) montoInput.value = payment.monto || '';
    if (metodoSelect) metodoSelect.value = payment.metodo || '';
    if (fechaInput) fechaInput.value = payment.fecha || '';
  }

  // Eliminar fila
  div.querySelector('.remove-payment-btn').onclick = () => {
    div.remove();
    updatePaymentSummary();
  };

  updatePaymentSummary();
};


// ==================== MANEJO DE HUÉSPEDES ====================

/**
 * Llena los campos manuales del huésped
 */
async function populateManualGuestFields(guestId) {
    if (!guestId) return;

    try {
        const response = await fetch(`api/guest.php?action=get&id=${guestId}`);
        if (!response.ok) throw new Error('No se pudo obtener el huésped');

        const guest = await response.json();

        document.getElementById('guestNameManual').value = guest.nombre || '';
        document.getElementById('guestNationalityManual').value = guest.nacionalidad || '';
        document.getElementById('guestPhoneManual').value = guest.telefono || '';
        document.getElementById('guestAddressManual').value = `${guest.calle || ''}, ${guest.ciudad || ''}, ${guest.estado || ''}`.replace(/^, |, ,/g, '').trim();
        document.getElementById('guestRFCManual').value = guest.rfc || '';
        document.getElementById('guestEmailManual').value = guest.email || '';

    } catch (error) {
        console.error('Error al llenar datos del huésped:', error);
        showAlert('Error al cargar los datos del cliente', 'error');
    }
}

/**
 * Obtiene la capacidad de una habitación
 */
async function getRoomCapacity(roomId) {
    const room = await getRoomById(roomId);
    return room ? room.capacity : Infinity;
}

/**
 * Actualiza el estado del botón para añadir huéspedes
 */
async function updateGuestAddButtonState() {
    const addCheckinGuestBtn = document.getElementById('addCheckinGuestBtn');
    const guestCapacityWarning = document.getElementById('guestCapacityWarning');
    if (!addCheckinGuestBtn || !guestCapacityWarning) return;

    const currentRoomId = document.getElementById('reservationRoomSelect').value;
    const capacity = await getRoomCapacity(currentRoomId);
    const currentGuestCount = document.querySelectorAll('.checkin-guest-row').length;

    if (currentGuestCount >= capacity) {
        addCheckinGuestBtn.disabled = true;
        guestCapacityWarning.style.display = 'inline';
    } else {
        addCheckinGuestBtn.disabled = false;
        guestCapacityWarning.style.display = 'none';
    }
}

/**
 * Añade una fila de huésped en check-in
 */
function addCheckinGuestRow(guestName = '') {
    const currentRoomId = document.getElementById('reservationRoomSelect').value;
    const capacity = getRoomCapacity(currentRoomId);
    const currentGuestCount = document.querySelectorAll('.checkin-guest-row').length;

    if (currentGuestCount >= capacity) {
        updateGuestAddButtonState();
        return;
    }

    const newRow = document.createElement('div');
    newRow.classList.add('form-group', 'checkin-guest-row');
    const guestNumber = currentGuestCount + 1;
    const uniqueGuestIndex = Date.now();

    newRow.innerHTML = `
        <label for="checkinGuestName_${uniqueGuestIndex}"><i class="fas fa-user"></i> Nombre persona ${guestNumber}:</label>
        <input type="text" id="checkinGuestName_${uniqueGuestIndex}" name="checkinGuests[${uniqueGuestIndex}][name]" placeholder="Nombre completo" value="${guestName}">
        ${guestNumber > 1 ? '<button type="button" class="remove-guest-btn" title="Eliminar persona">&times;</button>' : ''}
    `;

    const removeBtn = newRow.querySelector('.remove-guest-btn');
    if (removeBtn) {
        removeBtn.addEventListener('click', (e) => {
            e.target.closest('.checkin-guest-row').remove();
            const rows = document.querySelectorAll('.checkin-guest-row');
            rows.forEach((row, index) => {
                const label = row.querySelector('label');
                if (label) {
                    label.innerHTML = `<i class="fas fa-user"></i> Nombre persona ${index + 1}:`;
                }
            });
            updateGuestAddButtonState();
        });
    }

    document.getElementById('checkinGuestsContainer').appendChild(newRow);
    updateGuestAddButtonState();
}

/**
 * Llena los huéspedes en check-in
 */
function populateCheckinGuests(guestsData = []) {
    document.getElementById('checkinGuestsContainer').innerHTML = '';
    if (guestsData.length > 0) {
        guestsData.forEach(guest => {
            addCheckinGuestRow(guest.name || '');
        });
        
        const firstRowRemoveBtn = document.querySelector('.checkin-guest-row:first-child .remove-guest-btn');
        if (document.querySelectorAll('.checkin-guest-row').length <= 1 && firstRowRemoveBtn) {
            firstRowRemoveBtn.remove();
        }
    } else {
        addCheckinGuestRow('');
    }
    updateGuestAddButtonState();
}

// ==================== MANEJO DE ARTÍCULOS EN CHECK-IN ====================

/**
 * Calcula el total de artículos en check-in
 */
function calculateCheckinItemsTotal() {
    const checkinItemsTotalSpan = document.getElementById('checkinItemsTotal');
    if (!checkinItemsTotalSpan) return;

    let itemsTotal = 0;
    const itemRows = document.querySelectorAll('.checkin-item-row');

    itemRows.forEach(row => {
        const checkbox = row.querySelector('.checkin-item-toggle');
        const priceInput = row.querySelector('.checkin-item-price-input');

        if (checkbox && priceInput) {
            const isDelivered = checkbox.checked;
            const price = parseFloat(priceInput.value) || 0;
            if (isDelivered && price > 0) {
                itemsTotal += price;
            }
        }
    });

    checkinItemsTotalSpan.textContent = `$${itemsTotal.toFixed(2)}`;
}

/**
 * Añade una fila de artículo en check-in
 */
function addCheckinItemRow(itemData = null) {
    const template = document.getElementById('additionalCheckinItemTemplate');
    if (!template) return;

    const templateContent = template.content.cloneNode(true);
    const newRow = templateContent.querySelector('.additional-item-row');
    const itemNameInput = newRow.querySelector('input[name*="[name]"]');
    const checkbox = newRow.querySelector('.checkin-item-toggle');
    const checkboxLabel = newRow.querySelector('.checkbox-label');
    const priceInput = newRow.querySelector('.checkin-item-price-input');

    const uniqueIndex = Date.now();
    const uniqueId = `custom_${uniqueIndex}`;
    
    newRow.querySelectorAll('[id*="{{index}}"]').forEach(el => {
        el.id = el.id.replace('{{index}}', uniqueIndex);
    });
    
    newRow.querySelectorAll('[name*="{{index}}"]').forEach(el => {
        el.name = el.name.replace('{{index}}', uniqueIndex);
    });

    if (itemData) {
        if (itemNameInput) itemNameInput.value = itemData.name || '';
        if (checkbox) checkbox.checked = itemData.delivered || false;
        if (priceInput) priceInput.value = itemData.price || '';
    }

    if (itemNameInput && checkboxLabel) {
        itemNameInput.addEventListener('input', () => {
            checkboxLabel.textContent = itemNameInput.value || 'Entregado';
        });
        checkboxLabel.textContent = itemData?.name || 'Entregado';
    }

    if (checkbox && priceInput) {
        checkbox.addEventListener('change', () => {
            priceInput.disabled = !isAdmin;
            calculateCheckinItemsTotal();
        });
        priceInput.disabled = !isAdmin;
        priceInput.addEventListener('input', calculateCheckinItemsTotal);
    }

    const removeBtn = newRow.querySelector('.remove-item-btn');
    if (removeBtn) {
        removeBtn.addEventListener('click', (e) => {
            e.target.closest('.additional-item-row').remove();
            calculateCheckinItemsTotal();
        });
    }

    document.getElementById('checkinItemsContainer').appendChild(templateContent);
    if (itemNameInput && !itemData) itemNameInput.focus();
    calculateCheckinItemsTotal();
}

function populateHotelPayments(pagos = []) {
  const container = document.getElementById("pagosHotelContainer");
  const template = document.getElementById("pagoHotelTemplate");
  container.innerHTML = ""; // Limpiar el contenedor antes

  pagos.forEach((pago, index) => {
    const clone = template.content.cloneNode(true);

    clone.querySelectorAll("[name]").forEach(el => {
      el.name = el.name.replace("{{index}}", index);
    });

    clone.querySelector('input[name*="[monto]"]').value = pago.monto || '';
    clone.querySelector('select[name*="[metodo]"]').value = pago.metodo || '';
    clone.querySelector('input[name*="[fecha]"]').value = pago.fecha || '';

    const div = clone.querySelector('.additional-payment-row');

    // Asignar el evento de eliminar
    div.querySelector('.remove-payment-btn').onclick = () => {
      div.remove();
      updatePaymentSummary();
    };

    container.appendChild(div);
  });

  updatePaymentSummary();
}


/**
 * Llena los artículos en check-in
 */
function populateCheckinItems(itemsData = {}) {
    document.querySelectorAll('.additional-item-row').forEach(row => row.remove());

    const standardItemRows = document.querySelectorAll('.checkin-item-row:not(.additional-item-row)');
    standardItemRows.forEach(row => {
        const checkbox = row.querySelector('.checkin-item-toggle');
        const priceInput = row.querySelector('.checkin-item-price-input');

        if (!checkbox || !priceInput) return;

        const nameMatch = checkbox.name.match(/checkinItems\[(.*?)\]/);
        if (nameMatch && nameMatch[1]) {
            const itemNameKey = nameMatch[1];
            const savedItem = itemsData[itemNameKey];
            checkbox.checked = savedItem?.delivered || false;
            priceInput.value = savedItem?.price !== undefined ? savedItem?.price : (priceInput.defaultValue || '');
        } else {
            checkbox.checked = false;
            priceInput.value = priceInput.defaultValue || '';
        }
    });

    Object.keys(itemsData).forEach(key => {
        if (key.startsWith('custom_')) {
            addCheckinItemRow(itemsData[key]);
        }
    });

    setupStandardCheckinItems();
    calculateCheckinItemsTotal();
}

/**
 * Configura los artículos estándar en check-in
 */
function setupStandardCheckinItems() {
    const standardItemRows = document.querySelectorAll('.checkin-item-row:not(.additional-item-row)');
    standardItemRows.forEach(row => {
        const checkbox = row.querySelector('.checkin-item-toggle');
        const priceInput = row.querySelector('.checkin-item-price-input');

        if (checkbox && priceInput) {
            checkbox.addEventListener('change', () => {
                calculateCheckinItemsTotal();
            });
            priceInput.addEventListener('input', calculateCheckinItemsTotal);
        }
    });
}

// ==================== MANEJO DE FORMULARIO DE RESERVACIÓN ====================

/**
 * Maneja el envío del formulario de reservación
 */
async function handleReservationSubmit(event) {
    event.preventDefault();
   
    const totalReserva = calculateReservationTotal();
    const selectedRoomId = document.getElementById('reservationRoomSelect').value;
    const isAvailable = await validateRoomAvailability(selectedRoomId);
    
    if (!isAvailable) {
        const room = await getRoomById(selectedRoomId);
        const statusText = getRoomStatusText(room?.status || 'Desconocido');
        showAlert(`Error: La habitación "${room?.type || ''} ${room?.number || ''}" no está disponible (Estado: ${statusText}). No se puede guardar la reserva.`, 'error');
        updateSubmitButtonState();
        return;
    }

    const formData = new FormData(document.getElementById('reservationForm'));
    const selectedGuestId = formData.get('guestId');
    const manualGuestName = formData.get('guestNameManual')?.trim();
    let guestName = 'Huésped';
    
    if (selectedGuestId) {
        const guest = await getGuestById(selectedGuestId);
        guestName = guest ? guest.nombre : guestName;
    } else if (manualGuestName) {
        guestName = manualGuestName;
    }

    // Preparar datos complejos
   const anticipo = {
  monto: document.getElementById('paymentAnticipoMonto')?.value || '',
  metodo: document.getElementById('paymentAnticipoMetodo')?.value || '',
  ticket: document.getElementById('paymentAnticipoTicket')?.value || '',
};

    const verification = {
        dateTime: formData.get('verification[dateTime]'),
        whatsAppVerified: formData.get('verification[whatsAppVerified]'),
        senderName: formData.get('verification[senderName]'),
    };
    
const pagosExtra = [];
document.querySelectorAll('#pagosExtraContainer .additional-payment-row').forEach(row => {
    pagosExtra.push({
        monto: row.querySelector('input[name*="[monto]"]').value || '',
        metodo: row.querySelector('select[name*="[metodo]"]').value || '',
        clave: row.querySelector('input[name*="[clave]"]').value || '',
        autorizacion: row.querySelector('input[name*="[autorizacion]"]').value || '',
        fecha: row.querySelector('input[name*="[fecha]"]').value || '',
    });
});

// Pagos en Hotel
const pagosHotel = [];
document.querySelectorAll('#pagosHotelContainer .additional-payment-row').forEach(row => {
    pagosHotel.push({
        monto: row.querySelector('input[name*="[monto]"]').value || '',
        metodo: row.querySelector('select[name*="[metodo]"]').value || '',
        fecha: row.querySelector('input[name*="[fecha]"]').value || '',
    });
});

    const checkinGuests = [];
    document.querySelectorAll('.checkin-guest-row').forEach(row => {
        const nameInput = row.querySelector('input[name*="[name]"]');
        if (nameInput && nameInput.value.trim()) {
            checkinGuests.push({ name: nameInput.value.trim() });
        }
    });
    
    const checkinItems = {};
    document.querySelectorAll('.checkin-item-row').forEach(row => {
        const checkbox = row.querySelector('.checkin-item-toggle');
        const priceInput = row.querySelector('.checkin-item-price-input');
        const nameInput = row.querySelector('input[name*="[name]"]');

        if (checkbox && priceInput) {
            const nameMatch = checkbox.name.match(/checkinItems\[(.*?)\]/);
            if (nameMatch && nameMatch[1]) {
                const itemNameKey = nameMatch[1];
                const isDelivered = checkbox.checked;
                const price = parseFloat(priceInput.value) || 0;
                let itemName = row.querySelector('.checkbox-label')?.textContent || itemNameKey;

                if (itemNameKey.startsWith('custom_') && nameInput) {
                    itemName = nameInput.value.trim() || `Custom Item ${itemNameKey.split('_')[1]}`;
                }

                checkinItems[itemNameKey] = {
                    name: itemName,
                    delivered: isDelivered,
                    price: price,
                };
            }
        }
    });

    const receptionistName = document.getElementById('receptionistName')?.value || 'N/A';
    const selectedStatus = formData.get('reservationStatus');

    const reservationData = {
        id: document.getElementById('reservationId').value || undefined,
        resourceId: selectedRoomId,
        title: `Reserva ${guestName}`,
        start: document.getElementById('checkInDate').value,
        end: document.getElementById('checkOutDate').value,
        color: document.getElementById('reservationColor').value || '#FFD700',
        extendedProps: {
            guestId: selectedGuestId,
            guestNameManual: manualGuestName,
            status: formData.get('reservationStatus') || 'RESERVACION_PREVIA',
            rate: parseFloat(formData.get('reservationRate')) || 0,
            iva: parseFloat(formData.get('reservationIVA')) || 0,
            ish: parseFloat(formData.get('reservationISH')) || 0,
            inapamDiscount: document.getElementById('inapamDiscount').checked,
            inapamCredential: formData.get('inapamCredential'),
            inapamDiscountValue: parseFloat(formData.get('inapamDiscountValue')) || 0,
            inapamDiscountType: document.getElementById('inapamDiscountType')?.value || 'porcentaje',
            notes: formData.get('reservationNotes') || '',
            anticipo,
            verification,
            pagosExtra,
            pagosHotel: pagosHotel,
            totalReserva: totalReserva,
            checkinGuests,
            checkinItems,
            receptionistName: document.getElementById('receptionistName').value || ''

        }
    };    

    try {
        const result = await saveReservation(reservationData);
    
 if (result.success) {
    closeReservationModal();
    showAlert(currentEditingReservationId ? 'Reserva actualizada con éxito' : 'Reserva creada con éxito', 'success');

    const props = reservationData.extendedProps || {}; // ✅ Antes que cualquier uso

    populateHotelPayments(props.pagosHotel || []);

    if (currentEditingReservationId) {
        // 🔄 Editar evento ya existente en el calendario
        const event = calendarInstance.getEventById(currentEditingReservationId);
        if (event) {
            event.setProp('title', reservationData.title);
            event.setStart(reservationData.start);
            event.setEnd(reservationData.end);
            event.setExtendedProp('guestId', props.guestId);
            event.setExtendedProp('guestNameManual', props.guestNameManual);
            event.setExtendedProp('status', props.status);
            event.setExtendedProp('rate', props.rate);
            event.setExtendedProp('iva', props.iva);
            event.setExtendedProp('ish', props.ish);
            event.setExtendedProp('inapamDiscount', props.inapamDiscount);
            event.setExtendedProp('inapamCredential', props.inapamCredential);
            event.setExtendedProp('inapamDiscountValue', props.inapamDiscountValue);
            event.setExtendedProp('inapamDiscountType', props.inapamDiscountType);

            event.setExtendedProp('notes', props.notes);
            event.setExtendedProp('anticipo', props.anticipo);
            event.setExtendedProp('verification', props.verification);
            event.setExtendedProp('pagosExtra', props.pagosExtra);
            event.setExtendedProp('checkinGuests', props.checkinGuests);
            event.setExtendedProp('checkinItems', props.checkinItems);
            event.setExtendedProp('receptionistName', props.receptionistName);
            event.setProp('backgroundColor', reservationData.color || statusColorMap[props.status] || statusColorMap['default']);
        }
    } else {
        // ➕ Añadir nuevo evento
        calendarInstance.addEvent({
            id: result.insert_id,
            title: reservationData.title,
            start: reservationData.start,
            end: reservationData.end,
            color: reservationData.color,
            resourceId: reservationData.resourceId,
            extendedProps: reservationData.extendedProps
        });
    }
}
 else {
            showAlert('Error al guardar la reserva: ' + (result.error || 'Error desconocido'), 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al conectar con el servidor', 'error');
    }
   await renderCalendar()   
   if (typeof cargarAnticipos === 'function') {
    cargarAnticipos(); // Actualiza la tabla de anticipos dinámicamente
}
}

// ==================== FUNCIONES AUXILIARES ====================

/**
 * Muestra una alerta al usuario
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.classList.add('fade-out');
        setTimeout(() => alertDiv.remove(), 500);
    }, 3000);
}

/**
 * Obtiene el texto descriptivo del estado de una habitación
 */
function getRoomStatusText(statusCode) {
    const statusMap = {
        [ROOM_STATUS.DISPONIBLE]: 'Disponible',
        [ROOM_STATUS.OCUPADA]: 'Ocupada',
        [ROOM_STATUS.MANTENIMIENTO]: 'Mantenimiento',
        [ROOM_STATUS.LIMPIEZA]: 'Limpieza',
        [ROOM_STATUS.BLOQUEADA]: 'Bloqueada',
    };
    return statusMap[statusCode] || statusCode || 'Desconocido';
}
/**
 * Cambia entre pestañas en el modal
 */
function switchTab(targetTabId) {
    const tabContents = document.querySelectorAll('.tab-content');
    const tabLinks = document.querySelectorAll('.tab-link');
    
    tabContents.forEach(content => {
        content.classList.toggle('active', content.id === targetTabId);
    });
    
    tabLinks.forEach(link => {
        link.classList.toggle('active', link.dataset.tab === targetTabId);
    });
}
  
// También actualizar al cargar nuevos campos dinámicamente
const addExtraPaymentBtn = document.getElementById("addExtraPaymentBtn");
addExtraPaymentBtn?.addEventListener("click", () => {
    setTimeout(() => {
        const newInputs = document.querySelectorAll('#pagosExtraContainer .payment-amount-input');
        newInputs.forEach(input => {
            input.addEventListener("input", updatePaymentSummary);
        });
    }, 50);
});

// ==================== INICIALIZACIÓN ====================

document.addEventListener('DOMContentLoaded', function () {
    // Obtener elementos
    calendarEl = document.getElementById('calendar');
    dynamicContentEl = document.getElementById('dynamic-content');
    reservationModal = document.getElementById('reservationModal');
    reservationSubmitBtn = document.getElementById('reservationSubmitBtn');
    roomAvailabilityWarning = document.getElementById('roomAvailabilityWarning');

    // Activar comportamiento de tabs al hacer clic
   document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', () => {
      switchTab(link.dataset.tab);
    });
  });
  

    // Configurar navegación del menú lateral
   const navLinks = document.querySelectorAll('.sidebar ul li a');
navLinks.forEach(link => {
    link.addEventListener('click', (event) => {
        event.preventDefault();

        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        const section = link.textContent.trim();

        if (section === 'Cerrar sesión') {
            window.location.href = 'logout.php';
            return;
        }

        dynamicContentEl.innerHTML = '';
        dynamicContentEl.style.display = 'none';
        calendarEl.style.display = 'none';

        if (section === 'Calendario') {
            calendarEl.style.display = 'block';
            renderCalendar();
        } else if (section === 'Habitaciones') {
            renderRoomsSection();
        } else if (section === 'Huéspedes') {
            renderGuestsSection();
        } else if (section === 'Anticipos') {
            renderAnticiposSection();
        } else if (section === 'Reservas') {
            renderReservationsSection();
        } else if (section === 'Configuración') {
           renderConfiguracionSection();
        } else if (section === 'Añadir usuario') {
            renderAddUserSection();
        }
    });
});


     
    // Mostrar calendario por defecto al cargar
    renderCalendar();
    calendarEl.style.display = 'block';
    dynamicContentEl.style.display = 'none';
}); 

// =================== RENDER RESERVATIONS SECTION ===================
function renderReservationsSection() {
    dynamicContentEl.innerHTML = `
        <div class="reservations-header mb-4">
            <h3 class="fw-bold text-uppercase" style="color: #E67E22;">
                <i class="fas fa-calendar-check me-2"></i> 
                Gestión de Reservas
            </h3>
            <p class="text-muted">Visualiza y gestiona todas las reservas del sistema</p>
        </div>
          <div class="card-body p-0">
  <div class="p-3">
    <div class="row g-2 mb-3">
      <div class="col-md-3">
        <input type="text" id="filterGuest" class="form-control" placeholder="Buscar huésped...">
      </div>
      <div class="col-md-2">
        <input type="text" id="filterRoom" class="form-control" placeholder="Habitación">
      </div>
      <div class="col-md-3">
        <input type="date" id="filterStartDate" class="form-control">
      </div>
      <div class="col-md-3">
        <input type="date" id="filterEndDate" class="form-control">
      </div>
      <div class="col-md-1 d-grid">
        <button class="btn btn-outline-orange" onclick="applyReservationFilters()">
          <i class="fas fa-filter"></i>
        </button>
      </div>
    </div>
  </div>
        <div class="card shadow-sm border-0 overflow-hidden">
            <div class="card-header bg-gradient-orange text-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Lista de Reservas</h5>
                    <button class="btn btn-sm btn-light text-orange">
                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light-orange">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Huésped</th>
                                <th>Habitación</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Noches</th>
                                <th>Total</th>
                                <th>Pagado</th>
                                <th>Ticket</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="reservationsTableBody" class="bg-white">
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <div class="spinner-border text-orange" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                    <p class="mt-2 mb-0">Cargando reservas...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <style>
            .bg-gradient-orange {
                background: linear-gradient(135deg, #E67E22, #D35400);
                border: none;
            }
            .bg-light-orange {
                background-color: #FEF5E7;
            }
            .btn-outline-orange {
                color: #E67E22;
                border-color: #E67E22;
            }
            .btn-outline-orange:hover {
                background-color: #E67E22;
                color: white;
            }
            .text-orange {
                color: #E67E22 !important;
            }
            .status-badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-block;
                min-width: 80px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                text-color: #fff;
            }
            .btn-action {
                width: 32px;
                height: 32px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                margin: 0 2px;
                transition: all 0.2s;
            }
            .btn-action:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            .print-admin-btn {
                color: #E67E22;
                background-color: rgba(230, 126, 34, 0.1);
                border: 1px solid rgba(230, 126, 34, 0.3);
            }
            .print-recep-btn {
                color: #D35400;
                background-color: rgba(211, 84, 0, 0.1);
                border: 1px solid rgba(211, 84, 0, 0.3);
            }
            .cancel-btn {
                color: #E74C3C;
                background-color: rgba(231, 76, 60, 0.1);
                border: 1px solid rgba(231, 76, 60, 0.3);
            }
            .table > :not(:first-child) {
                border-top: none;
            }
            .table > thead > tr > th {
                border-bottom: 2px solid #F5B041;
                color: #7E5109;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.75rem;
                letter-spacing: 0.5px;
            }
            .table > tbody > tr {
                transition: all 0.2s;
                border-left: 3px solid transparent;
            }
            .table > tbody > tr:hover {
                background-color: #FFF5E6;
                border-left: 3px solid #E67E22;
            }
        </style>
    `;

    dynamicContentEl.style.display = 'block';
    calendarEl.style.display = 'none';

    fetch('api/reservatsection.php?action=get_all')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('reservationsTableBody');
            tbody.innerHTML = '';

            if (data.success && Array.isArray(data.data)) {
                if (data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="12" class="text-center">No hay reservas registradas.</td></tr>`;
                    return;
                }

                data.data.forEach(reservation => {
                    const checkIn = new Date(reservation.start_date);
                    const checkOut = new Date(reservation.end_date);
                    const msInDay = 1000 * 60 * 60 * 24;
                    const nights = Math.ceil((checkOut - checkIn) / msInDay);

                    const rate = parseFloat(reservation.rate || 0);
                    const iva = parseFloat(reservation.iva || 0);
                    const ish = parseFloat(reservation.ish || 0);
                    const discount = reservation.inapamDiscount ? parseFloat(reservation.inapamDiscountValue || 0) : 0;

                    const subtotal = rate * nights;
                    const ivaAmount = subtotal * (iva / 100);
                    const ishAmount = subtotal * (ish / 100);
                    const total = subtotal + ivaAmount + ishAmount - discount;

                    // Anticipo y pagos extra
                    const anticipo = reservation.anticipo && reservation.anticipo.monto ? parseFloat(reservation.anticipo.monto) : 0;

                    let pagosExtra = 0;
                    if (Array.isArray(reservation.pagosExtra)) {
                        pagosExtra = reservation.pagosExtra.reduce((sum, pago) => {
                            const monto = parseFloat(pago.monto || 0);
                            return sum + (isNaN(monto) ? 0 : monto);
                        }, 0);
                    }

                    const pagadoTotal = anticipo + pagosExtra;
                    const saldo = total - pagadoTotal;

                    const ticket = reservation.anticipo && reservation.anticipo.ticket ? reservation.anticipo.ticket : '—';

                    tbody.innerHTML += `
                        <tr>
                            <td>${reservation.id}</td>
                            <td>${reservation.guestNameManual || 'Sin nombre'}</td>
                            <td>${reservation.resourceId}</td>
                            <td>${reservation.start_date}</td>
                            <td>${reservation.end_date}</td>
                            <td>${nights}</td>
                            <td>$${total.toFixed(2)}</td>
                            <td>$${pagadoTotal.toFixed(2)}</td>
                            <td>${ticket}</td>
                            <td>$${saldo.toFixed(2)}</td>
                            <td><span class="status-badge" style="background-color: ${reservation.color};">${reservation.status}</span></td>
                            <td>
                                <button title="Imprimir PDF Admin" class="print-admin-btn" onclick="printPDFAdmin(${reservation.id})">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                <button title="Imprimir PDF Recepción" class="print-recep-btn" onclick="printPDFRecepcion(${reservation.id})">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error al obtener reservas.</td></tr>`;
                console.error('Error:', data);
            }
        })
        .catch(err => {
            const tbody = document.getElementById('reservationsTableBody');
            tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error al cargar reservas.</td></tr>`;
            console.error('Fetch error:', err);
        });
}
// ==================== SECCIÓN DE AÑADIR USUARIO ====================


function renderAddUserSection() {
    const html = `
    <div class="mt-4 mb-5" style="padding: 0 30px;">
        <div class="bg-white shadow-sm rounded p-4 mb-4">
            <h4 class="mb-4 text-dark"><i class="fas fa-user-plus me-2"></i> Añadir Nuevo Usuario</h4>
            <form id="addUserForm" class="row g-4" novalidate>
                <div class="col-md-4">
                    <label for="username" class="form-label"><i class="fas fa-user me-1"></i> Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="ej. juan23" required>
                </div>
                <div class="col-md-4">
                    <label for="fullName" class="form-label"><i class="fas fa-user-tag me-1"></i> Nombre Completo</label>
                    <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Nombre completo" required>
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label"><i class="fas fa-envelope me-1"></i> Correo</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" required>
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label"><i class="fas fa-user-shield me-1"></i> Rol</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Seleccione un rol</option>
                        <option value="admin">Administrador</option>
                        <option value="user">Recepcionista</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label"><i class="fas fa-lock me-1"></i> Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña segura" required>
                </div>
                <div class="col-md-4">
                    <label for="confirmPassword" class="form-label"><i class="fas fa-lock me-1"></i> Confirmar</label>
                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Repetir contraseña" required>
                </div>
                <div class="col-12 text-end mt-3">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-user-plus me-1"></i> Crear Usuario
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white shadow-sm rounded p-4">
            <h5 class="mb-3"><i class="fas fa-users me-2"></i> Usuarios Registrados</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        <!-- Se agregan dinámicamente -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    `;

    dynamicContentEl.innerHTML = html;
    dynamicContentEl.style.display = 'block';
    calendarEl.style.display = 'none';

    const form = document.getElementById('addUserForm');
    const userTableBody = document.getElementById('userTableBody');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const username = form.username.value.trim();
        const fullName = form.fullName.value.trim();
        const email = form.email.value.trim();
        const role = form.role.value;
        const password = form.password.value;
        const confirmPassword = form.confirmPassword.value;

        if (password !== confirmPassword) {
            showAlert('Las contraseñas no coinciden', 'error');
            return;
        }

        try {
            const response = await fetch('api/users.php?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, fullName, email, role, password })
            });

            const result = await response.json();

            if (result.success) {
                showAlert('Usuario añadido exitosamente', 'success');
                form.reset();
                cargarUsuarios(); // recargar tabla
            } else {
                showAlert(result.message || 'Error al añadir el usuario', 'error');
            }
        } catch (error) {
            console.error(error);
            showAlert('Error de conexión con el servidor', 'error');
        }
    });

    async function cargarUsuarios() {
        try {
            const res = await fetch('api/users.php?action=list');
            const data = await res.json();

            if (data.success && Array.isArray(data.users)) {
                userTableBody.innerHTML = '';
                data.users.forEach((user, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${user.username}</td>
                        <td>${user.fullName}</td>
                        <td>${user.email}</td>
                        <td>${user.role === 'admin' ? 'Administrador' : 'Recepcionista'}</td>
                        <td>
                            <button class="btn btn-sm btn-warning me-1" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </td>
                    `;
                    userTableBody.appendChild(row);
                });
            } else {
                showAlert('No se pudieron cargar los usuarios', 'error');
            }
        } catch (err) {
            console.error(err);
            showAlert('Error al obtener los usuarios', 'error');
        }
    }

    cargarUsuarios();
}

   
// =================== RENDER ROOMS SECTION ===================
function renderRoomsSection() {
window.handleRoomFormSubmit = handleRoomFormSubmit;
window.editRoom = editRoom;
window.deleteRoom = deleteRoom;

    dynamicContentEl.innerHTML = `
        <div class="container mt-4">
            <h3><i class="fas fa-bed me-2"></i> Gestión de Habitaciones</h3>

            <!-- Formulario fijo en la pantalla -->
            <div id="roomFormContainer" class="border rounded-3 shadow-sm p-4 my-4 bg-white">
                <h5 id="formTitle"><i class="fas fa-door-open me-2"></i> Nueva Habitación</h5>
                <form id="roomForm">
                    <input type="hidden" id="roomId">
                    <div class="row g-3 mt-2">
                        <div class="col-md-3"><input type="text" class="form-control" id="roomType" placeholder="Tipo" required></div>
                        <div class="col-md-2"><input type="text" class="form-control" id="roomNumber" placeholder="Número" required></div>
                        <div class="col-md-2"><input type="number" class="form-control" id="roomBeds" placeholder="Camas" required></div>
                        <div class="col-md-2"><input type="number" class="form-control" id="roomCapacity" placeholder="Capacidad" required></div>
                        <div class="col-md-3"><input type="number" step="0.01" class="form-control" id="roomPrice" placeholder="Precio" required></div>
                        <div class="col-md-3">
                            <select id="roomInapam" class="form-select">
                                <option value="0">Sin INAPAM</option>
                                <option value="1">Con INAPAM</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="roomStatus" class="form-select" required>
                                <option value="disponible">Disponible</option>
                                <option value="ocupada">Ocupada</option>
                                <option value="mantenimiento">Mantenimiento</option>
                                <option value="limpieza">Limpieza</option>
                                <option value="bloqueada">Bloqueada</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <button type="reset" class="btn btn-secondary" onclick="clearRoomForm()"><i class="fas fa-times me-1"></i> Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar</button>
                    </div>
                </form>
            </div>

            <!-- Lista de habitaciones -->
            <div id="roomsListContainer" class="list-group shadow-sm"></div>
        </div>
    `;

    dynamicContentEl.style.display = 'block';
    calendarEl.style.display = 'none';

    fetchRooms().then(renderRoomsList);

    document.getElementById('roomForm').addEventListener('submit', handleRoomFormSubmit);
}

function renderRoomsList(rooms) {
    const container = document.getElementById('roomsListContainer');
    container.innerHTML = '';

    rooms.forEach(room => {
        const inapam = room.inapam == 1 ? `<span class="badge bg-success me-1"><i class="fas fa-check-circle"></i> INAPAM</span>` : '';
        const estado = `<span class="badge bg-${getRoomStateColor(room.status)}">${getRoomStatusText(room.status)}</span>`;
        container.innerHTML += `
            <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-door-open fa-2x text-secondary"></i>
                    <div>
                        <div><strong>${room.type}</strong> <small class="text-muted">#${room.number}</small></div>
                        <div class="small text-muted">
                            🛏️ ${room.beds} camas · 👥 ${room.capacity} personas · 💵 $${parseFloat(room.price).toFixed(2)} ${inapam} ${estado}
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-warning" onclick='editRoom(${JSON.stringify(room)})'><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger" onclick='deleteRoom(${room.id})'><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>
        `;
    });
}


function editRoom(room) {
    document.getElementById('formTitle').textContent = 'Editar Habitación';
    document.getElementById('roomId').value = room.id;
    document.getElementById('roomType').value = room.type;
    document.getElementById('roomNumber').value = room.number;
    document.getElementById('roomBeds').value = room.beds;
    document.getElementById('roomCapacity').value = room.capacity;
    document.getElementById('roomPrice').value = room.price;
    document.getElementById('roomInapam').value = room.inapam;
    document.getElementById('roomStatus').value = room.status;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function clearRoomForm() {
    document.getElementById('formTitle').textContent = 'Nueva Habitación';
    document.getElementById('roomForm').reset();
    document.getElementById('roomId').value = '';
}

async function handleRoomFormSubmit(e) {
    e.preventDefault();
    const roomData = {
        id: document.getElementById('roomId').value || null,
        type: document.getElementById('roomType').value,
        number: document.getElementById('roomNumber').value,
        beds: document.getElementById('roomBeds').value,
        capacity: document.getElementById('roomCapacity').value,
        price: document.getElementById('roomPrice').value,
        inapam: document.getElementById('roomInapam').value,
        status: document.getElementById('roomStatus').value
    };

    const result = await saveRoom(roomData);
    if (result.success) {
        showAlert('Habitación guardada con éxito', 'success');
        clearRoomForm();
        fetchRooms().then(renderRoomsList);
    } else {
        showAlert('Error al guardar habitación', 'error');
    }
}

async function deleteRoom(id) {
    if (!confirm('¿Eliminar esta habitación?')) return;

    const result = await fetch(`api/rooms.php?action=delete&id=${id}`, { method: 'DELETE' });
    const json = await result.json();

    if (json.success) {
        showAlert('Habitación eliminada', 'success');
        fetchRooms().then(renderRoomsList);
    } else {
        showAlert('Error al eliminar habitación', 'error');
    }
}

function getRoomStateColor(status) {
    switch (status) {
        case 'disponible': return 'success';
        case 'ocupada': return 'warning';
        case 'mantenimiento': return 'secondary';
        case 'limpieza': return 'info';
        case 'bloqueada': return 'danger';
        default: return 'dark';
    }
}


// =================== GUESTS SECTION ===================
function openGuestModal() {
    const modal = document.getElementById('guestModal');
    const title = document.getElementById('guestModalTitle');
    if (modal && title) {
      title.textContent = 'Nuevo Cliente';
      modal.style.display = 'flex';
    }
  }
  
  function closeGuestModal() {
    const modal = document.getElementById('guestModal');
    if (modal) {
      modal.style.display = 'none';
      modal.classList.remove('show');
      document.body.classList.remove('modal-open');
      const backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) backdrop.remove();
    }
  }
  document.getElementById('guestForm').addEventListener('submit', async function (e) {
    e.preventDefault();
  
    const guestData = {
      nombre: document.getElementById('guestName').value,
      nacionalidad: document.getElementById('guestNationality').value,
      telefono: document.getElementById('guestPhone').value,
      calle: document.getElementById('guestAddress').value,
      ciudad: document.getElementById('guestCity').value,
      estado: document.getElementById('guestState').value,
      rfc: document.getElementById('guestRFC').value,
      email: document.getElementById('guestEmail').value
    };
  
    try {
      const response = await fetch('api/guest.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(guestData)
      });
  
      const result = await response.json();
  
      if (result.success && result.id) {
        // Agrega el nuevo huésped al select
        const select = document.getElementById('reservationGuestSelect');
        const option = document.createElement('option');
        option.value = result.id;
        option.textContent = guestData.nombre;
        option.selected = true;
        select.appendChild(option);
  
        alert('Cliente añadido exitosamente');
        closeGuestModal();
      } else {
        alert('Error al guardar cliente: ' + result.message);
      }
    } catch (error) {
      alert('Error de red al guardar cliente.');
      console.error(error);
    }
  });

 
 // =================== FUNCIONES DE IMPRESIÓN PDF ===================
window.printPDFAdmin = function(reservationId) {
    window.open(`pdf/pdf_admin.php?id=${reservationId}`, '_blank');
};

window.printPDFRecepcion = function(reservationId) {
    window.open(`pdf/pdf_recepcion.php?id=${reservationId}`, '_blank');
};
// =================== FUNCIONES FILTROS ===================
function applyReservationFilters() {
  const guestInput = document.getElementById('filterGuest').value.toLowerCase();
  const roomInput = document.getElementById('filterRoom').value;
  const startDate = document.getElementById('filterStartDate').value;
  const endDate = document.getElementById('filterEndDate').value;

  const rows = document.querySelectorAll('#reservationsTableBody tr');
  rows.forEach(row => {
    const guest = row.children[1]?.textContent.toLowerCase() || '';
    const room = row.children[2]?.textContent || '';
    const start = row.children[3]?.textContent || '';
    const end = row.children[4]?.textContent || '';

    const matchesGuest = guest.includes(guestInput);
    const matchesRoom = room.includes(roomInput);
    const matchesStart = !startDate || start >= startDate;
    const matchesEnd = !endDate || end <= endDate;

    row.style.display = (matchesGuest && matchesRoom && matchesStart && matchesEnd) ? '' : 'none';
  });
}
window.applyReservationFilters = applyReservationFilters;

// ========================== renderAnticiposSection =============================
function renderAnticiposSection() {
  window.renderAnticiposSection = renderAnticiposSection;

 dynamicContentEl.innerHTML = `

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold text-orange"><i class="fas fa-search me-2"></i> Buscar Reservas</h4>
  <div class="d-flex gap-2">
    <input type="text" id="searchReservaNombre" class="form-control form-control-sm" placeholder="Buscar por nombre o fecha">
    <button type="button" class="btn btn-sm btn-outline-orange" onclick="buscarYMostrarReservas()">
      <i class="fas fa-search me-1"></i> Buscar
    </button>
  </div>
</div>

<!-- Aquí va el div de resultados -->
<div id="resultadosReservas" class="mt-2"></div>


  <!-- 🧾 Formulario de anticipo -->
  <div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-gradient-orange text-white py-2">
      <h6 class="mb-0 fw-bold"><i class="fas fa-receipt me-2"></i> Registrar Anticipo</h6>
    </div>
    <div class="card-body p-3">
      <form id="formAnticipo" class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Nombre del huésped</label>
          <input type="text" id="anticipoGuest" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">ID Reserva</label>
          <input type="text" id="anticipoReserva" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Entrada</label>
          <input type="date" id="entrada" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Salida</label>
          <input type="date" id="salida" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Tipo Habitación</label>
          <input type="text" id="tipoHabitacion" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Personas</label>
          <input type="number" id="personas" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Tarifa</label>
          <input type="number" step="0.01" id="tarifa" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Total</label>
          <input type="number" step="0.01" id="total" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
          <label class="form-label">Anticipo</label>
          <input type="number" step="0.01" id="anticipo" class="form-control form-control-sm" required oninput="calcularTotalPesos()">
        </div>
        <div class="col-md-2">
          <label class="form-label">Saldo</label>
          <input type="number" step="0.01" id="saldo" class="form-control form-control-sm">
        </div>
        <div class="col-md-3">
          <label class="form-label">Método de pago</label>
          <select id="metodo_pago" class="form-select form-select-sm" onchange="mostrarMonedaSiEsEfectivo()">
            <option value="">Selecciona método...</option>
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Tarjeta Débito">Tarjeta Débito</option>
            <option value="Tarjeta Crédito">Tarjeta Crédito</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Tasa de cambio</label>
          <input type="number" step="0.01" id="tasaCambio" class="form-control form-control-sm">
        </div>
        <div class="col-md-3 d-none" id="campoMoneda">
          <label class="form-label">Moneda</label>
          <select id="selectMoneda" class="form-select form-select-sm" onchange="actualizarTasaCambio()">
            <option value="">Selecciona moneda</option>
            <option value="USD">Dólar (USD)</option>
            <option value="EUR">Euro (EUR)</option>
            <option value="GBP">Libra esterlina (GBP)</option>
            <option value="CAD">Dólar canadiense (CAD)</option>
            <option value="JPY">Yen japonés (JPY)</option>
            <option value="BRL">Real brasileño (BRL)</option>
            <option value="ARS">Peso argentino (ARS)</option>
            <option value="COP">Peso colombiano (COP)</option>
            <option value="CLP">Peso chileno (CLP)</option>
            <option value="MXN">Peso mexicano (MXN)</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Total en pesos (MXN)</label>
          <input type="number" id="totalPesos" class="form-control form-control-sm" readonly>
        </div>
        <div class="col-md-12">
          <label class="form-label">Observaciones</label>
          <textarea id="observaciones" class="form-control form-control-sm" rows="2"></textarea>
        </div>
        <div class="col-12 text-end mt-3">
          <button type="submit" class="btn btn-sm btn-outline-orange">
            <i class="fas fa-save me-1"></i> Guardar Anticipo
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- 📋 Tabla de anticipos -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-gradient-orange text-white py-2">
      <h6 class="mb-0 fw-bold"><i class="fas fa-coins me-2"></i> Anticipos Registrados</h6>
    </div>
    <div class="card-body p-3">
      <div class="d-flex gap-2 mb-3">
        <input type="text" id="filtroNombre" class="form-control form-control-sm" placeholder="Filtrar por huésped" oninput="filtrarAnticipos()">
        <input type="date" id="filtroFecha" class="form-control form-control-sm" onchange="filtrarAnticipos()">
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Huésped</th>
              <th>Anticipo</th>
              <th>Método</th>
              <th>Tasa</th>
              <th>Total MXN</th>
              <th>Fecha</th>
              <th>Imprimir</th>
            </tr>
          </thead>
          <tbody id="tablaAnticiposBody">
            <!-- JS Insertará datos -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- 🧾 Modal Detalle Anticipo -->
  <div class="modal fade" id="modalDetalleAnticipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
      <div class="modal-content border-0">
        <div class="modal-header bg-orange text-white">
          <h5 class="modal-title fw-bold"><i class="fas fa-file-alt me-2"></i> Detalle del Anticipo</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label fw-semibold mb-0">Huésped:</label>
              <div id="modalGuest" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Reserva:</label>
              <div id="modalReserva" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Fecha:</label>
              <div id="modalFecha" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Método de pago:</label>
              <div id="modalMetodo" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Tasa de cambio:</label>
              <div id="modalTasa" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Anticipo:</label>
              <div id="modalAnticipo" class="text-muted small"></div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold mb-0">Total en pesos (MXN):</label>
              <div id="modalTotal" class="text-muted small"></div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold mb-0">Observaciones:</label>
              <div id="modalObs" class="text-muted small"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 px-4 pb-3">
          <button class="btn btn-outline-orange w-100" id="btnVerRecibo">
            <i class="fas fa-print me-2"></i> Ver Recibo PDF
          </button>
        </div>
      </div>
    </div>
  </div>
<style>
  /* Colores base */
  :root {
    --naranja-primario: #e67e22;
    --naranja-oscuro: #d35400;
    --naranja-claro: #fef5e7;
    --naranja-hover: #fff2e6;
  }

  /* Textos y botones */
  .text-orange {
    color: var(--naranja-primario) !important;
  }
  .bg-orange {
    background-color: var(--naranja-primario) !important;
  }
  .bg-gradient-orange {
    background: linear-gradient(135deg, var(--naranja-primario), var(--naranja-oscuro));
    color: white !important;
  }
  .btn-outline-orange {
    color: var(--naranja-primario);
    border-color: var(--naranja-primario);
  }
  .btn-outline-orange:hover {
    background-color: var(--naranja-primario);
    color: white;
  }

  /* Formularios */
  .form-control:focus, .form-select:focus {
    border-color: var(--naranja-primario);
    box-shadow: 0 0 0 0.2rem rgba(230, 126, 34, 0.25);
  }
  .form-label {
    color: var(--naranja-oscuro);
    font-weight: 600;
  }

  /* Card */
  .card {
    border-radius: 10px;
    overflow: hidden;
    border: none;
  }
  .card-header {
    background-color: var(--naranja-primario);
    color: white;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.9rem;
  }

  /* Tabla */
  .table thead {
    background-color: var(--naranja-claro);
    color: var(--naranja-oscuro);
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.8rem;
  }
  .table-hover tbody tr:hover {
    background-color: var(--naranja-hover);
  }
  .table th, .table td {
    padding: 0.5rem;
  }

  /* Modal */
  .modal-content {
    border-radius: 10px;
    border: none;
    background-color: #fffaf5;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  }
  .modal-header.bg-orange {
    background-color: var(--naranja-primario);
    color: white;
  }
  .modal-header .btn-close {
    filter: invert(1);
  }

  /* Títulos */
  h4, h5, h6 {
    font-weight: 700;
  }

  /* Buscador */
  .form-control-sm, .form-select-sm {
    font-size: 0.85rem;
    padding: 0.4rem 0.6rem;
  }
    /* Corrección de capas del modal y el fondo */
.modal-backdrop.show {
  background-color: rgba(0, 0, 0, 0.4); /* Transparencia bonita */
  z-index: 1040 !important;
}

.modal {
  z-index: 1050 !important;
}

.modal-content {
  z-index: 1055 !important;
  position: relative;
}

</style>

`;

  dynamicContentEl.style.display = 'block';
  calendarEl.style.display = 'none';

  document.getElementById('filtroNombre').value = '';
  document.getElementById('filtroFecha').value = '';

  cargarAnticipos();


 // Fin renderAnticiposSection

 document.getElementById('formAnticipo').addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = {
      guest: document.getElementById('anticipoGuest').value.trim(),
      reserva: document.getElementById('anticipoReserva').value.trim(),
      entrada: document.getElementById('entrada').value,
      salida: document.getElementById('salida').value,
      tipoHabitacion: document.getElementById('tipoHabitacion').value,
      personas: document.getElementById('personas').value,
      tarifa: document.getElementById('tarifa').value,
      total: document.getElementById('total').value,
      anticipo: document.getElementById('totalPesos').value,
      saldo: document.getElementById('saldo').value,
      observaciones: document.getElementById('observaciones').value,
      fecha: new Date().toISOString().slice(0, 10),
      metodo_pago: document.getElementById('metodo_pago').value,
      selectMoneda: document.getElementById("selectMoneda").value,
      tasaCambio: document.getElementById('tasaCambio').value,
      anticipoOriginal: document.getElementById('anticipo').value,
    };

    if (!data.guest || !data.reserva || !data.anticipo || !data.metodo_pago) {
      alert('Completa todos los campos requeridos');
      return;
    }

    try {
      const res = await fetch('api/anticipos.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });

      const result = await res.json();
      if (result.success) {
        alert('✅ Anticipo registrado exitosamente');
        cargarAnticipos(); // Recargar la tabla al guardar
      } else {
        alert('❌ Error al registrar anticipo');
      }
    } catch (error) {
      alert('❌ Error de red');
      console.error(error);
    }
  });

  window.printAnticipoDesdeFormulario = function () {
    const reservaId = document.getElementById('anticipoReserva').value;
    if (reservaId) {
      window.open(`pdf/pdf_anticipo.php?id=${reservaId}`, '_blank');
    } else {
      alert("Especifica el ID de la reserva para imprimir el recibo");
    }
  };
}
  window.buscarYMostrarReservas = async function () {
    const query = document.getElementById('searchReservaNombre').value.toLowerCase();
    const contenedor = document.getElementById('resultadosReservas');
    contenedor.innerHTML = '<p class="text-muted">Buscando...</p>';

    try {
      const res = await fetch('api/reservatsection.php?action=get_all');
      const json = await res.json();

      if (json.success && Array.isArray(json.data)) {
        const coincidencias = json.data.filter(r => {
          const nombre = r.guestNameManual?.toLowerCase() || '';
          const fecha = r.start_date;
          return nombre.includes(query) || fecha.includes(query);
        });

        if (coincidencias.length === 0) {
          contenedor.innerHTML = '<p class="text-danger">No se encontraron coincidencias.</p>';
          return;
        }

        contenedor.innerHTML = '<ul class="list-group">';
        coincidencias.forEach(r => {
          contenedor.innerHTML += `
            <li class="list-group-item list-group-item-action" style="cursor:pointer"
                onclick='llenarFormularioReserva(${JSON.stringify(r)})'>
              <strong>${r.guestNameManual}</strong> – Hab. ${r.resourceId} (${r.start_date} a ${r.end_date})
            </li>`;
        });
        contenedor.innerHTML += '</ul>';
      } else {
        contenedor.innerHTML = '<p class="text-danger">Error al obtener reservas.</p>';
      }
    } catch (err) {
      console.error(err);
      contenedor.innerHTML = '<p class="text-danger">Error de conexión.</p>';
    }
  };

window.llenarFormularioReserva = function (reserva) {
  console.log('Reserva seleccionada:', reserva);

  // Datos básicos
  document.getElementById('anticipoGuest').value = reserva.guestNameManual || '';
  document.getElementById('anticipoReserva').value = reserva.id || '';
  document.getElementById('entrada').value = reserva.start_date || '';
  document.getElementById('salida').value = reserva.end_date || '';
  document.getElementById('tipoHabitacion').value = reserva.resourceId || '';
  document.getElementById('personas').value = reserva.guestId || '';
 document.getElementById('tarifa').value = reserva.rate || '';
 

  const totalReserva = parseFloat(reserva.totalReserva || 0);
  document.getElementById('total').value = totalReserva.toFixed(2);

  let totalPagado = 0;

  try {
    if (reserva.anticipo) {
      const anticipo = typeof reserva.anticipo === 'string' ? JSON.parse(reserva.anticipo) : reserva.anticipo;
      totalPagado += parseFloat(anticipo.monto || 0);
    }
  } catch (err) {
    console.warn('Error en anticipo:', err);
  }

  try {
    if (reserva.pagosHotel) {
      const pagosHotel = typeof reserva.pagosHotel === 'string' ? JSON.parse(reserva.pagosHotel) : reserva.pagosHotel;
      pagosHotel.forEach(p => totalPagado += parseFloat(p.monto || 0));
    }
  } catch (err) {
    console.warn('Error en pagosHotel:', err);
  }

  try {
    if (reserva.pagosExtra) {
      const pagosExtra = typeof reserva.pagosExtra === 'string' ? JSON.parse(reserva.pagosExtra) : reserva.pagosExtra;
      pagosExtra.forEach(p => totalPagado += parseFloat(p.monto || 0));
    }
  } catch (err) {
    console.warn('Error en pagosExtra:', err);
  }

  const saldo = totalReserva - totalPagado;
  document.getElementById('saldo').value = saldo.toFixed(2);

  // Limpiar campos de pago
  document.getElementById('metodo_pago').value = '';
  document.getElementById('selectMoneda').value = '';
  document.getElementById('tasaCambio').value = '';
  document.getElementById('totalPesos').value = '';
};


let anticiposGlobal = [];

async function cargarAnticipos() {
  const body = document.getElementById('tablaAnticiposBody');
  body.innerHTML = `<tr><td colspan="8" class="text-center">Cargando anticipos...</td></tr>`;

  try {
    const res = await fetch('api/anticipos.php?action=list');
    const json = await res.json();

    if (json.success && Array.isArray(json.data)) {
      anticiposGlobal = json.data;
      mostrarAnticipos(anticiposGlobal);
    } else {
      body.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al obtener anticipos.</td></tr>`;
    }
  } catch (err) {
    console.error(err);
    body.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error de conexión.</td></tr>`;
  }
}

function mostrarAnticipos(lista) {
  const body = document.getElementById('tablaAnticiposBody');
  body.innerHTML = '';

  if (lista.length === 0) {
    body.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No hay anticipos.</td></tr>`;
    return;
  }

  lista.forEach(item => {
    const anticipo = parseFloat(item.anticipo || 0);
    const tasa = parseFloat(item.tasa_cambio || 0);
    const totalMXN = anticipo; // Ya viene en pesos, no volver a convertir

body.innerHTML += `
  <tr style="cursor:pointer" onclick='verDetalleAnticipo(${JSON.stringify(item)})'>
    <td>${item.id}</td>
    <td>${item.guest}</td>
    <td>$${anticipo.toFixed(2)}</td>
    <td>${item.metodo_pago}</td>
    <td>${tasa > 0 ? tasa.toFixed(2) : '-'}</td>
    <td>$${totalMXN.toFixed(2)}</td>
    <td>${item.fecha}</td>
    <td>
      <button class="btn btn-sm btn-outline-orange" title="Ver recibo" onclick="event.stopPropagation(); window.open('pdf/pdf_anticipo.php?id=${item.id}', '_blank')">
        <i class="fas fa-print"></i>
      </button>
    </td>
  </tr>
`;

  });
}


window.cargarAnticipos = cargarAnticipos;


window.mostrarMonedaSiEsEfectivo = function () {
  const metodo = document.getElementById('metodo_pago').value;
  const monedaDiv = document.getElementById('campoMoneda');
  const tasaInput = document.getElementById('tasaCambio');
  const selectMoneda = document.getElementById('selectMoneda');

  if (metodo === 'Efectivo') {
    monedaDiv.classList.remove('d-none');
  } else {
    monedaDiv.classList.add('d-none');
    selectMoneda.value = '';
    tasaInput.value = '';
    document.getElementById('totalPesos').value = '';
  }
  calcularTotalPesos();
};
window.actualizarTasaCambio = async function () {
  const moneda = document.getElementById('selectMoneda').value;
  const campoTasa = document.getElementById('tasaCambio');

  if (!moneda || moneda === 'MXN') {
    campoTasa.value = '';
    calcularTotalPesos();
    return;
  }

  try {
    const url = `https://open.er-api.com/v6/latest/${moneda}`;
    const res = await fetch(url);
    const data = await res.json();

    console.log("Moneda seleccionada:", moneda);
    console.log("Respuesta de la API:", data);

    if (data.result === "success" && data.rates && data.rates.MXN) {
      const tasa = data.rates.MXN;
      campoTasa.value = tasa.toFixed(2);
      calcularTotalPesos(); // ← Esto actualiza el total en pesos
    } else {
      campoTasa.value = '';
      alert('❌ No se pudo obtener la tasa de cambio.');
    }
  } catch (err) {
    console.error('❌ Error al obtener la tasa de cambio:', err);
    campoTasa.value = '';
    alert('❌ Error de red al obtener la tasa.');
  }
};


window.calcularTotalPesos = function () {
  const metodo = document.getElementById('metodo_pago').value;
  const anticipo = parseFloat(document.getElementById('anticipo').value || 0);
  const tasa = parseFloat(document.getElementById('tasaCambio').value || 1);
  const campoTotal = document.getElementById('totalPesos');

  if (metodo === 'Efectivo' && !isNaN(anticipo) && !isNaN(tasa)) {
    campoTotal.value = (anticipo * tasa).toFixed(2);
  } else {
    campoTotal.value = anticipo.toFixed(2);
  }
};


window.mostrarMonedaSiEsEfectivo = mostrarMonedaSiEsEfectivo;

// Hacer accesibles globalmente
window.actualizarTasaCambio = actualizarTasaCambio;
window.verDetalleAnticipo = function (item) {
  const anticipo = parseFloat(item.anticipo || 0);
  const tasa = parseFloat(item.tasa_cambio || 0);
  const moneda = item.selectMoneda || 'MXN';

  let textoAnticipo = '';

  if (moneda !== 'MXN' && tasa > 0) {
    const original = (anticipo / tasa).toFixed(2);
    textoAnticipo = `${original} ${moneda} → $${anticipo.toFixed(2)} MXN`;
  } else {
    textoAnticipo = `$${anticipo.toFixed(2)} MXN`;
  }

  // Llenar campos del modal
  document.getElementById('modalGuest').textContent = item.guest;
  document.getElementById('modalReserva').textContent = item.reserva_id || '-';
  document.getElementById('modalMetodo').textContent = item.metodo_pago;
  document.getElementById('modalTasa').textContent = tasa > 0 ? tasa : '-';
  document.getElementById('modalAnticipo').textContent = textoAnticipo;
  document.getElementById('modalTotal').textContent = `$${anticipo.toFixed(2)}`;
  document.getElementById('modalFecha').textContent = item.fecha;
  document.getElementById('modalObs').textContent = item.observaciones || '—';

  document.getElementById('btnVerRecibo').onclick = () => {
    window.open(`pdf/pdf_anticipo.php?id=${item.id}`, '_blank');
  };

  const modal = new bootstrap.Modal(document.getElementById('modalDetalleAnticipo'));
  modal.show();
};


function renderConfiguracionSection() {
  window.renderConfiguracionSection = renderConfiguracionSection;

  dynamicContentEl.innerHTML = `
    <div class="config-header mb-4">
      <h3 class="fw-bold text-uppercase" style="color: #E67E22;">
        <i class="fas fa-cogs me-2"></i> Configuración General
      </h3>

      <form id="configForm" class="card p-4 shadow-sm border-0 mb-4">
        <h5><i class="fas fa-percent me-1"></i> Impuestos</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="iva" class="form-label">IVA (%)</label>
            <input type="number" step="0.01" id="iva" name="iva" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label for="ish" class="form-label">ISH (%)</label>
            <input type="number" step="0.01" id="ish" name="ish" class="form-control" required>
          </div>
        </div>
        <button type="submit" class="btn btn-outline-orange mt-3">
          <i class="fas fa-save me-1"></i> Guardar Configuración
        </button>
      </form>

      <div class="card p-4 shadow-sm border-0 mb-4">
        <h5><i class="fas fa-calendar-plus me-1"></i> Crear Nueva Temporada</h5>
        <form id="formNuevaTemporada" class="row g-3">
          <div class="col-md-4">
            <label for="nombreTemporada" class="form-label">Nombre</label>
            <input type="text" id="nombreTemporada" class="form-control" placeholder="Ej. Temporada Alta" required>
          </div>
          <div class="col-md-4">
            <label for="inicioTemporada" class="form-label">Inicio</label>
            <input type="date" id="inicioTemporada" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label for="finTemporada" class="form-label">Fin</label>
            <input type="date" id="finTemporada" class="form-control" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-plus"></i> Crear Temporada
            </button>
          </div>
        </form>
      </div>

      <div class="card p-4 shadow-sm border-0 mb-4">
        <h5><i class="fas fa-calendar me-1"></i> Temporadas Registradas</h5>
        <div class="table-responsive">
          <table class="table table-striped table-bordered" id="tablaTemporadas">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>

      <div class="card p-4 shadow-sm border-0 mb-4">
        <h5><i class="fas fa-tag me-1"></i> Asignar Tarifas por Habitaciones</h5>
        <form id="formTarifaHabitacion" class="row g-3">
          <div class="col-md-4">
            <label for="habitacionSelect" class="form-label">Habitaciones</label>
            <select id="habitacionSelect" class="form-select" multiple required></select>
            <small class="text-muted">Selecciona varias con Ctrl o Shift</small>
          </div>
          <div class="col-md-4">
            <label for="temporadaSelect" class="form-label">Temporada</label>
            <select id="temporadaSelect" class="form-select" required>
              <option value="">Selecciona una temporada</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="precioTarifa" class="form-label">Precio ($)</label>
            <input type="number" step="0.01" id="precioTarifa" class="form-control" placeholder="Ej. 2500" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-success">
              <i class="fas fa-plus me-1"></i> Asignar Tarifas
            </button>
          </div>
        </form>
      </div>

      <div class="card p-4 shadow-sm border-0">
        <h5><i class="fas fa-table me-1"></i> Lista de Tarifas</h5>
        <table class="table table-sm table-bordered" id="tablaTarifas">
          <thead>
            <tr>
              <th>Habitación</th>
              <th>Temporada</th>
              <th>Precio ($)</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
  `;

cargarTemporadasTabla();
  dynamicContentEl.style.display = 'block';
  calendarEl.style.display = 'none';

  // Cargar configuración inicial
  fetch('api/config.php?action=get')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        document.getElementById('iva').value = data.iva || '';
        document.getElementById('ish').value = data.ish || '';
      }
    });

  // Guardar Configuración
  document.getElementById('configForm').onsubmit = async (e) => {
    e.preventDefault();
    const iva = parseFloat(document.getElementById('iva').value) || 0;
    const ish = parseFloat(document.getElementById('ish').value) || 0;

    const res = await fetch('api/config.php?action=update', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ iva, ish })
    });
    const json = await res.json();
    showAlert(json.success ? 'Configuración guardada' : 'Error', json.success ? 'success' : 'error');
  };

  // Crear temporada
  document.getElementById('formNuevaTemporada').onsubmit = async (e) => {
    e.preventDefault();
    const nombre = document.getElementById('nombreTemporada').value;
    const fecha_inicio = document.getElementById('inicioTemporada').value;
    const fecha_fin = document.getElementById('finTemporada').value;

    const res = await fetch('api/temporadas.php?action=add', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nombre, fecha_inicio, fecha_fin })
    });
    const json = await res.json();
    showAlert(json.success ? 'Temporada creada' : 'Error al crear', json.success ? 'success' : 'error');
    if (json.success) renderConfiguracionSection();
  };

  cargarTemporadasTabla();
  cargarTarifasTabla();
  cargarHabitacionesYTemporadas();
}

function cargarTemporadasTabla() {
  fetch('api/temporadas.php?action=get_all')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const tbody = document.querySelector("#tablaTemporadas tbody");
        tbody.innerHTML = '';
        data.temporadas.forEach(t => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td><input value="${t.nombre}" data-id="${t.id}" class="form-control form-control-sm nombre-temporada"></td>
            <td><input type="date" value="${t.fecha_inicio}" class="form-control form-control-sm fecha-inicio"></td>
            <td><input type="date" value="${t.fecha_fin}" class="form-control form-control-sm fecha-fin"></td>
            <td class="d-flex gap-1">
              <button class="btn btn-sm btn-primary btn-guardar" data-id="${t.id}">
                <i class="fas fa-save"></i>
              </button>
              <button class="btn btn-sm btn-danger btn-eliminar" data-id="${t.id}">
                <i class="fas fa-trash-alt"></i>
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

        // Guardar cambios
        document.querySelectorAll(".btn-guardar").forEach(btn => {
          btn.onclick = async () => {
            const row = btn.closest("tr");
            const id = btn.dataset.id;
            const nombre = row.querySelector(".nombre-temporada").value;
            const fecha_inicio = row.querySelector(".fecha-inicio").value;
            const fecha_fin = row.querySelector(".fecha-fin").value;

            const res = await fetch('api/temporadas.php?action=update', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id, nombre, fecha_inicio, fecha_fin })
            });
            const json = await res.json();
            showAlert(json.success ? 'Temporada actualizada' : 'Error', json.success ? 'success' : 'error');
          };
        });

        // Eliminar temporada
        document.querySelectorAll(".btn-eliminar").forEach(btn => {
          btn.onclick = async () => {
            const id = btn.dataset.id;
            if (!confirm("¿Eliminar esta temporada?")) return;

            const res = await fetch(`api/temporadas.php?action=delete&id=${id}`);
            const json = await res.json();
            showAlert(json.success ? 'Temporada eliminada' : 'Error al eliminar', json.success ? 'success' : 'error');
            if (json.success) cargarTemporadasTabla();
          };
        });
      }
    });
}

function cargarHabitacionesYTemporadas() {
  // Habitaciones
  fetch('api/habitaciones.php?action=get_all')
    .then(res => res.json())
    .then(data => {
      const select = document.getElementById('habitacionSelect');
      data.habitaciones.forEach(h => {
        const opt = document.createElement('option');
        opt.value = h.id;
        opt.textContent = `${h.type} - ${h.number}`;
        select.appendChild(opt);
      });
    });

  // Temporadas
  fetch('api/temporadas.php?action=get_all')
    .then(res => res.json())
    .then(data => {
      const select = document.getElementById('temporadaSelect');
      data.temporadas.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = `${t.nombre} (${t.fecha_inicio} al ${t.fecha_fin})`;
        select.appendChild(opt);
      });
    });
}

function cargarTarifasTabla() {
  fetch('api/tarifas_habitacion.php?action=get_all')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const tbody = document.querySelector("#tablaTarifas tbody");
        tbody.innerHTML = '';
        data.tarifas.forEach(t => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${t.habitacion}</td>
            <td>${t.temporada} (${t.fecha_inicio} al ${t.fecha_fin})</td>
            <td>$${parseFloat(t.tarifa).toFixed(2)}</td>
            <td>
              <button class="btn btn-sm btn-danger btn-eliminar-tarifa" data-id="${t.id}">
                <i class="fas fa-trash-alt"></i>
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

        document.querySelectorAll(".btn-eliminar-tarifa").forEach(btn => {
          btn.onclick = async () => {
            const id = btn.dataset.id;
            if (!confirm("¿Eliminar esta tarifa?")) return;

            const res = await fetch(`api/tarifas_habitacion.php?action=delete&id=${id}`);
            const json = await res.json();
            showAlert(json.success ? 'Tarifa eliminada' : 'Error al eliminar', json.success ? 'success' : 'error');
            if (json.success) cargarTarifasTabla();
          };
        });
      }
    });

  // Evento guardar tarifas
 document.getElementById('formTarifaHabitacion').onsubmit = async (e) => {
  e.preventDefault();
  const habitaciones = Array.from(document.getElementById('habitacionSelect').selectedOptions).map(opt => opt.value);
  const temporada = document.getElementById('temporadaSelect').value;
  const precio = parseFloat(document.getElementById('precioTarifa').value) || 0;

  // 👇 Agrega esto
  console.log({ habitaciones, temporada, precio });

  const res = await fetch('api/tarifas_habitacion.php?action=add', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ habitaciones, temporada, precio })
  });
 }
}