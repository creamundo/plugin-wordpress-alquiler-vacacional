(function (wp) {
    if (!wp || !wp.element) {
        return;
    }

    const { createElement: el, useState, useEffect, useMemo } = wp.element;
    const adminSettings = window.ctvrAdmin || {};
    const REST_ROOT = (adminSettings.restUrl || '').replace(/\/$/, '');
    const NONCE = adminSettings.nonce || '';

    function restFetch(path, options = {}) {
        const url = `${REST_ROOT}/${path}`;
        const config = Object.assign({ method: 'GET', headers: { 'X-WP-Nonce': NONCE } }, options);
        if (config.body && typeof config.body !== 'string') {
            config.body = JSON.stringify(config.body);
            config.headers['Content-Type'] = 'application/json';
        }
        return window.fetch(url, config).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(data.message || 'Error de red');
                error.data = data;
                throw error;
            }
            return data;
        });
    }

    function useAsync(callback, deps) {
        const [state, setState] = useState({ loading: true, error: null, data: null });
        useEffect(() => {
            let mounted = true;
            setState({ loading: true, error: null, data: null });
            callback()
                .then((data) => mounted && setState({ loading: false, error: null, data }))
                .catch((error) => mounted && setState({ loading: false, error: error.message, data: null }));
            return () => {
                mounted = false;
            };
        }, deps);
        return state;
    }

    function DashboardApp() {
        const { loading, error, data } = useAsync(() => restFetch('stats'), []);

        if (loading) {
            return el('p', null, 'Cargando estadísticas…');
        }
        if (error) {
            return el('p', { className: 'notice notice-error' }, error);
        }

        const events = data.events || {};
        const cards = [
            { label: 'Vistas de calendario', value: events.calendar_view || 0 },
            { label: 'Rangos seleccionados', value: events.date_range_selected || 0 },
            { label: 'Solicitudes enviadas', value: events.form_submission || 0 },
            { label: 'Formularios confirmados', value: events.form_sent || 0 }
        ];

        return el('div', { className: 'ctvr-stat-grid' },
            cards.map((card) => el('div', { className: 'ctvr-stat-card', key: card.label },
                el('strong', null, card.value),
                el('small', null, card.label)
            )),
            el('div', { className: 'ctvr-card', style: { gridColumn: '1 / -1' } },
                el('h2', null, 'Rangos más consultados'),
                el('ul', null, Object.entries(data.top_ranges || {}).map(([range, count]) => el('li', { key: range }, `${range}: ${count}`)))
            ),
            el('div', { className: 'ctvr-card', style: { gridColumn: '1 / -1' } },
                el('h2', null, 'Días con más solicitudes'),
                el('ul', null, Object.entries(data.top_entries || {}).map(([day, count]) => el('li', { key: day }, `${day}: ${count}`)))
            )
        );
    }

    function AvailabilityApp() {
        const today = new Date();
        const [currentMonth, setCurrentMonth] = useState(new Date(today.getFullYear(), today.getMonth(), 1));
        const [days, setDays] = useState([]);
        const [loading, setLoading] = useState(false);
        const [selection, setSelection] = useState({ start: null, end: null });
        const [status, setStatus] = useState('available');
        const [price, setPrice] = useState('');
        const [notice, setNotice] = useState('');

        useEffect(() => {
            if (status !== 'available') {
                setPrice('');
            }
        }, [status]);

        useEffect(() => {
            setLoading(true);
            setNotice('');
            restFetch(`calendar?year=${currentMonth.getFullYear()}&month=${currentMonth.getMonth() + 1}`)
                .then((data) => setDays(data.days || []))
                .finally(() => setLoading(false));
        }, [currentMonth]);

        function selectDay(day) {
            if (!selection.start || (selection.start && selection.end)) {
                setSelection({ start: day.day, end: null });
                return;
            }

            const startDate = new Date(selection.start);
            const endDate = new Date(day.day);
            if (endDate < startDate) {
                setSelection({ start: day.day, end: null });
                return;
            }

            setSelection({ start: selection.start, end: day.day });
        }

        function applyRange() {
            if (!selection.start || !selection.end) {
                setNotice('Selecciona un rango para aplicar los cambios.');
                return;
            }
            if (status === 'available' && (price === '' || Number(price) <= 0)) {
                setNotice('Introduce un precio válido para el rango.');
                return;
            }
            const body = { start: selection.start, end: selection.end, status, price: price === '' ? null : Number(price) };
            restFetch('availability', { method: 'POST', body })
                .then(() => {
                    setNotice('Rango actualizado correctamente.');
                    restFetch(`calendar?year=${currentMonth.getFullYear()}&month=${currentMonth.getMonth() + 1}`)
                        .then((data) => setDays(data.days || []));
                })
                .catch((error) => setNotice(error.message || 'No se pudo actualizar el rango.'));
        }

        function goMonth(offset) {
            const clone = new Date(currentMonth);
            clone.setMonth(clone.getMonth() + offset);
            setCurrentMonth(clone);
        }

        function renderDay(day) {
            const classes = ['ctvr-day'];
            if (day.status === 'blocked') {
                classes.push('ctvr-day-blocked');
            } else if (day.price === null) {
                classes.push('ctvr-day-unpriced');
            } else {
                classes.push('ctvr-day-available');
            }
            if (selection.start && day.day === selection.start) {
                classes.push('ctvr-day-selected');
            }
            if (selection.end && day.day === selection.end) {
                classes.push('ctvr-day-selected');
            }
            const label = `${new Date(day.day).getDate()} (${day.status})`;
            return el('button', { key: day.day, type: 'button', className: classes.join(' '), onClick: () => selectDay(day) }, label,
                day.price !== null && el('span', { className: 'ctvr-day-price' }, Number(day.price).toFixed(2))
            );
        }

        return el('div', null,
            el('div', { className: 'ctvr-calendar-header' },
                el('button', { type: 'button', className: 'ctvr-nav-button', onClick: () => goMonth(-1) }, '←'),
                el('div', { className: 'ctvr-month-label' }, currentMonth.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })),
                el('button', { type: 'button', className: 'ctvr-nav-button', onClick: () => goMonth(1) }, '→')
            ),
            loading ? el('p', null, 'Cargando…') : el('div', { className: 'ctvr-calendar-admin' }, days.map(renderDay)),
            el('div', { className: 'ctvr-admin-toolbar' },
                el('select', { value: status, onChange: (event) => setStatus(event.target.value) },
                    el('option', { value: 'available' }, 'Asignar precio'),
                    el('option', { value: 'blocked' }, 'Marcar como ocupado'),
                    el('option', { value: 'unpriced' }, 'Sin precio')
                ),
                status === 'available' && el('input', {
                    type: 'number',
                    min: 0,
                    step: '0.01',
                    value: price,
                    placeholder: 'Precio por noche',
                    onChange: (event) => setPrice(event.target.value)
                }),
                el('button', { type: 'button', className: 'button button-primary', onClick: applyRange }, 'Aplicar al rango')
            ),
            notice && el('p', { className: 'notice notice-info' }, notice)
        );
    }

    function RequestsApp() {
        const [requests, setRequests] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState('');

        function load() {
            setLoading(true);
            setError('');
            restFetch('requests')
                .then((data) => {
                    setRequests(data.requests || []);
                    setLoading(false);
                })
                .catch((err) => {
                    setError(err.message || 'No se pudieron cargar las solicitudes');
                    setLoading(false);
                });
        }

        useEffect(load, []);

        function handleApprove(id) {
            restFetch(`requests/${id}/approve`, { method: 'POST' })
                .then(() => load())
                .catch((err) => setError(err.message));
        }

        function handleReject(id) {
            restFetch(`requests/${id}/reject`, { method: 'POST' })
                .then(() => load())
                .catch((err) => setError(err.message));
        }

        function handleDelete(id) {
            if (!window.confirm('¿Eliminar la solicitud seleccionada?')) {
                return;
            }
            restFetch(`requests/${id}`, { method: 'DELETE' })
                .then(() => load())
                .catch((err) => setError(err.message));
        }

        if (loading) {
            return el('p', null, 'Cargando solicitudes…');
        }
        if (error) {
            return el('p', { className: 'notice notice-error' }, error);
        }
        if (!requests.length) {
            return el('div', { className: 'ctvr-empty' }, 'Todavía no hay solicitudes registradas.');
        }

        return el('table', { className: 'widefat striped ctvr-admin-table' },
            el('thead', null, el('tr', null,
                ['ID', 'Fechas', 'Nombre', 'Personas', 'Importe', 'Estado', 'Acciones'].map((label) => el('th', { key: label }, label))
            )),
            el('tbody', null,
                requests.map((request) => {
                    const payload = request.payload || {};
                    const people = payload.people || 0;
                    const fullName = `${payload.name || ''} ${payload.surname || ''}`.trim();
                    return el('tr', { key: request.id },
                        el('td', null, request.id),
                        el('td', null, `${request.start_date} → ${request.end_date}`),
                        el('td', null, fullName || '—'),
                        el('td', null, people),
                        el('td', null, request.price_total ? `${Number(request.price_total).toFixed(2)} €` : '—'),
                        el('td', null, el('span', { className: `ctvr-badge ctvr-badge--${request.status}` }, request.status)),
                        el('td', null,
                            el('div', { className: 'ctvr-admin-toolbar' },
                                el('button', { type: 'button', className: 'button button-primary', onClick: () => handleApprove(request.id) }, 'Aprobar'),
                                el('button', { type: 'button', className: 'button', onClick: () => handleReject(request.id) }, 'Rechazar'),
                                el('button', { type: 'button', className: 'button button-link-delete', onClick: () => handleDelete(request.id) }, 'Eliminar')
                            )
                        )
                    );
                })
            )
        );
    }

    function WorkorderEditor({ reservation, onUpdated }) {
        const [workorder, setWorkorder] = useState(reservation.workorder || {});
        useEffect(() => {
            setWorkorder(reservation.workorder || {});
        }, [reservation.workorder]);

        const [saving, setSaving] = useState(false);
        const [message, setMessage] = useState('');
        const settings = workorder.settings || {};
        const payload = reservation.payload || {};
        const platformConfig = useMemo(() => {
            try {
                return JSON.parse(settings.platforms || '[]');
            } catch (error) {
                return [];
            }
        }, [settings.platforms]);

        function updateField(field, value) {
            setWorkorder((prev) => Object.assign({}, prev, { [field]: value }));
        }

        function updateServices(field, value) {
            const services = Object.assign({}, workorder.services || {});
            services[field] = value;
            updateField('services', services);
        }

        function updateChecklist(scope, location, itemId, checked) {
            const key = scope === 'entry' ? 'entry_checklist' : 'exit_checklist';
            const list = Object.assign({}, workorder[key] || {});
            if (!list[location]) {
                list[location] = {};
            }
            list[location][itemId] = checked;
            updateField(key, list);
        }

        function updatePurchase(index, field, value) {
            const purchases = (workorder.purchases || []).slice();
            purchases[index] = Object.assign({}, purchases[index] || {}, { [field]: value });
            updateField('purchases', purchases);
        }

        function addPurchase() {
            const purchases = (workorder.purchases || []).concat({ concept: '', amount: 0 });
            updateField('purchases', purchases);
        }

        function removePurchase(index) {
            const purchases = (workorder.purchases || []).slice();
            purchases.splice(index, 1);
            updateField('purchases', purchases);
        }

        function save() {
            setSaving(true);
            restFetch(`reservations/${reservation.id}/workorder`, { method: 'POST', body: workorder })
                .then(() => {
                    setSaving(false);
                    setMessage('Orden de trabajo guardada.');
                    onUpdated && onUpdated();
                })
                .catch((error) => {
                    setSaving(false);
                    setMessage(error.message || 'Error al guardar.');
                });
        }

        const areaLabels = {
            general: 'Tareas generales',
            kitchen: 'Cocina',
            bathroom: 'Baños',
            living: 'Salón',
            bedroom: 'Dormitorios',
            terrace: 'Terraza/Patio',
            garden: 'Jardín'
        };

        function renderChecklist(scope) {
            const allChecklists = workorder.checklists || {};
            const groups = allChecklists[scope] || {};
            return el('div', null,
                Object.entries(groups).map(([location, items]) => el('fieldset', { key: `${scope}-${location}` },
                    el('legend', null, areaLabels[location] || location),
                    items.map((item) => el('label', { key: item.id, className: 'ctvr-field ctvr-checkbox' },
                        el('input', {
                            type: 'checkbox',
                            checked: Boolean(
                                workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'] &&
                                workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'][location] &&
                                workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'][location][item.id]
                            ),
                            onChange: (event) => updateChecklist(scope, location, item.id, event.target.checked)
                        }),
                        item.title
                    ))
                ))
            );
        }

        const purchases = workorder.purchases || [];
        const cleaningHourPrice = Number(settings.cleaning_hour_price || 0);
        const keyDeliveryPrice = Number(settings.key_delivery_price || 0);
        const linenPrice = Number(settings.linen_cleaning_price || 0);
        const taxPercentage = Number(settings.tax_percentage || 0);
        const managementPercentage = Number(settings.management_percentage || 0);
        const entryHours = Number(workorder.entry_hours || 0);
        const exitHours = Number(workorder.exit_hours || 0);
        const entryCost = entryHours * cleaningHourPrice;
        const exitCost = exitHours * cleaningHourPrice;
        const servicesData = workorder.services || {};
        const keyCost = servicesData.key_delivery ? keyDeliveryPrice : 0;
        const linenCost = servicesData.linen ? linenPrice : 0;
        const purchasesCost = purchases.reduce((sum, purchase) => sum + Number(purchase.amount || 0), 0);
        const platformFee = (() => {
            const platform = servicesData.platform;
            if (!platform) {
                return 0;
            }
            const config = platformConfig.find((item) => item.name === platform);
            if (!config || !config.percentage) {
                return 0;
            }
            return Number(reservation.price_total || 0) * (Number(config.percentage) / 100);
        })();
        const taxHold = Number(reservation.price_total || 0) * (taxPercentage / 100);
        const subtotal = entryCost + exitCost + keyCost + linenCost + purchasesCost;
        const managementBase = Math.max(0, Number(reservation.price_total || 0) - platformFee - taxHold - subtotal);
        const managementFee = managementPercentage ? (managementBase * (managementPercentage / 100)) : 0;
        const totalDue = subtotal + managementFee;

        return el('div', { className: 'ctvr-card', style: { marginTop: '1rem' } },
            el('h3', null, `Orden de trabajo · ${payload.name || ''} ${payload.surname || ''}`),
            el('p', null, `Estancia: ${reservation.start_date} → ${reservation.end_date}`),
            el('div', { className: 'ctvr-form-grid' },
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Horas limpieza entrada'),
                    el('input', { type: 'number', min: 0, step: '0.5', value: workorder.entry_hours || '', onChange: (event) => updateField('entry_hours', event.target.value) }),
                    el('small', null, `Precio hora: ${cleaningHourPrice.toFixed(2)} €`)
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Horas limpieza salida'),
                    el('input', { type: 'number', min: 0, step: '0.5', value: workorder.exit_hours || '', onChange: (event) => updateField('exit_hours', event.target.value) }),
                    el('small', null, `Precio hora: ${cleaningHourPrice.toFixed(2)} €`)
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Entrega de llaves realizada'),
                    el('input', { type: 'checkbox', checked: Boolean(servicesData.key_delivery), onChange: (event) => updateServices('key_delivery', event.target.checked) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Gestión ropa de cama realizada'),
                    el('input', { type: 'checkbox', checked: Boolean(servicesData.linen), onChange: (event) => updateServices('linen', event.target.checked) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Plataforma'),
                    el('select', { value: servicesData.platform || '', onChange: (event) => updateServices('platform', event.target.value) },
                        el('option', { value: '' }, 'Directa / Ninguna'),
                        platformConfig.map((item) => el('option', { key: item.name, value: item.name }, `${item.name} (${item.percentage || 0}% )`))
                    )
                )
            ),
            el('details', { open: true },
                el('summary', null, 'Checklist de entrada'),
                renderChecklist('entry')
            ),
            el('details', null,
                el('summary', null, 'Checklist de salida'),
                renderChecklist('exit')
            ),
            el('div', null,
                el('h4', null, 'Compras realizadas'),
                purchases.map((purchase, index) => el('div', { className: 'ctvr-form-grid', key: index },
                    el('div', { className: 'ctvr-field' },
                        el('label', null, 'Concepto'),
                        el('input', { type: 'text', value: purchase.concept || '', onChange: (event) => updatePurchase(index, 'concept', event.target.value) })
                    ),
                    el('div', { className: 'ctvr-field' },
                        el('label', null, 'Importe (€)'),
                        el('input', { type: 'number', min: 0, step: '0.01', value: purchase.amount || '', onChange: (event) => updatePurchase(index, 'amount', event.target.value) })
                    ),
                    el('div', { className: 'ctvr-field' },
                        el('label', null, 'Eliminar'),
                        el('button', { type: 'button', className: 'button button-link-delete', onClick: () => removePurchase(index) }, 'Eliminar compra')
                    )
                )),
                el('button', { type: 'button', className: 'button', onClick: addPurchase }, 'Añadir compra')
            ),
            el('div', { className: 'ctvr-summary' },
                el('strong', null, 'Resumen económico'),
                el('span', null, `Horas entrada (${entryHours}h): ${entryCost.toFixed(2)} €`),
                el('span', null, `Horas salida (${exitHours}h): ${exitCost.toFixed(2)} €`),
                el('span', null, `Entrega llaves: ${keyCost.toFixed(2)} €`),
                el('span', null, `Ropa de cama: ${linenCost.toFixed(2)} €`),
                el('span', null, `Compras: ${purchasesCost.toFixed(2)} €`),
                platformFee ? el('span', null, `Comisión plataforma: ${platformFee.toFixed(2)} €`) : null,
                taxHold ? el('span', null, `Retención impuestos: ${taxHold.toFixed(2)} €`) : null,
                managementFee ? el('span', null, `Gestión (${managementPercentage}%): ${managementFee.toFixed(2)} €`) : null,
                el('strong', null, `Total a pagar: ${totalDue.toFixed(2)} €`)
            ),
            message && el('p', { className: 'notice notice-info' }, message),
            el('button', { type: 'button', className: 'button button-primary', disabled: saving, onClick: save }, saving ? 'Guardando…' : 'Guardar cambios')
        );
    }

    function ReservationsApp() {
        const [reservations, setReservations] = useState([]);
        const [expanded, setExpanded] = useState(null);
        const [filter, setFilter] = useState('');

        function load() {
            restFetch('reservations')
                .then((data) => setReservations(data.reservations || []))
                .catch(() => setReservations([]));
        }

        useEffect(load, []);

        const filtered = reservations.filter((reservation) => {
            if (!filter) {
                return true;
            }
            const haystack = JSON.stringify(reservation.payload || {}).toLowerCase();
            return haystack.includes(filter.toLowerCase());
        });

        function copyLink(token) {
            const url = `${window.location.origin}/?ctvr_token=${token}`;
            if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
                window.navigator.clipboard.writeText(url);
            }
            window.alert('Enlace copiado al portapapeles');
        }

        return el('div', null,
            el('div', { className: 'ctvr-filters' },
                el('input', {
                    type: 'search',
                    placeholder: 'Buscar por nombre, email, nacionalidad…',
                    value: filter,
                    onChange: (event) => setFilter(event.target.value)
                })
            ),
            !filtered.length ? el('div', { className: 'ctvr-empty' }, 'No hay reservas aprobadas que cumplan el criterio.') :
                el('div', null,
                    filtered.map((reservation) => {
                        const payload = reservation.payload || {};
                        const title = `${reservation.start_date} → ${reservation.end_date} · ${payload.name || ''} ${payload.surname || ''}`;
                        const expandedReservation = expanded === reservation.id;
                        return el('div', { key: reservation.id, className: 'ctvr-card', style: { marginBottom: '1rem' } },
                            el('div', { className: 'ctvr-admin-toolbar' },
                                el('strong', null, title),
                                el('span', { className: 'ctvr-pill' }, `${payload.people || 0} personas`),
                                el('button', { type: 'button', className: 'button', onClick: () => copyLink(reservation.public_token) }, 'Copiar enlace público'),
                                el('button', { type: 'button', className: 'button button-primary', onClick: () => setExpanded(expandedReservation ? null : reservation.id) }, expandedReservation ? 'Cerrar orden' : 'Ver orden de trabajo')
                            ),
                            expandedReservation && el(WorkorderEditor, { reservation, onUpdated: load })
                        );
                    })
                )
        );
    }

    function ChecklistApp() {
        const [items, setItems] = useState([]);
        const [form, setForm] = useState({ title: '', scope: 'both', location: 'general' });
        const [error, setError] = useState('');

        function load() {
            restFetch('checklists')
                .then((data) => {
                    setItems(data.checklists || []);
                    setError('');
                })
                .catch((err) => setError(err.message || 'No se pudieron cargar las tareas'));
        }

        useEffect(load, []);

        const grouped = useMemo(() => {
            const map = { entry: {}, exit: {} };
            items.forEach((item) => {
                if (item.scope === 'entry' || item.scope === 'both') {
                    map.entry[item.location] = map.entry[item.location] || [];
                    map.entry[item.location].push(item);
                }
                if (item.scope === 'exit' || item.scope === 'both') {
                    map.exit[item.location] = map.exit[item.location] || [];
                    map.exit[item.location].push(item);
                }
            });
            return map;
        }, [items]);

        function submit(event) {
            event.preventDefault();
            restFetch('checklists', { method: 'POST', body: form })
                .then(() => {
                    setForm({ title: '', scope: 'both', location: 'general' });
                    load();
                })
                .catch((err) => setError(err.message || 'Error al guardar la tarea'));
        }

        function toggleActive(item) {
            restFetch(`checklists/${item.id}`, { method: 'POST', body: Object.assign({}, item, { is_active: item.is_active ? 0 : 1 }) })
                .then(() => load());
        }

        function remove(item) {
            if (!window.confirm('¿Eliminar la tarea seleccionada?')) {
                return;
            }
            restFetch(`checklists/${item.id}`, { method: 'DELETE' }).then(() => load());
        }

        const areas = {
            general: 'Tareas generales',
            kitchen: 'Cocina',
            bathroom: 'Baños',
            living: 'Salón',
            bedroom: 'Dormitorios',
            terrace: 'Terraza/Patio',
            garden: 'Jardín'
        };

        return el('div', null,
            el('form', { className: 'ctvr-form-grid', onSubmit: submit, style: { marginBottom: '1.5rem' } },
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Título de la tarea'),
                    el('input', { type: 'text', value: form.title, required: true, onChange: (event) => setForm(Object.assign({}, form, { title: event.target.value })) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Ámbito'),
                    el('select', { value: form.scope, onChange: (event) => setForm(Object.assign({}, form, { scope: event.target.value })) },
                        el('option', { value: 'entry' }, 'Entrada'),
                        el('option', { value: 'exit' }, 'Salida'),
                        el('option', { value: 'both' }, 'Ambas')
                    )
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Estancia'),
                    el('select', { value: form.location, onChange: (event) => setForm(Object.assign({}, form, { location: event.target.value })) },
                        Object.entries(areas).map(([key, label]) => el('option', { key: key, value: key }, label))
                    )
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Guardar tarea'),
                    el('button', { type: 'submit', className: 'button button-primary' }, 'Crear tarea')
                )
            ),
            error && el('p', { className: 'notice notice-error' }, error),
            el('div', { className: 'ctvr-form-grid' },
                el('div', { className: 'ctvr-card' },
                    el('h3', null, 'Checklist de entrada'),
                    Object.entries(grouped.entry).map(([location, tasks]) => el('details', { key: location, open: true },
                        el('summary', null, areas[location] || location),
                        el('ul', null, tasks.map((task) => el('li', { key: task.id },
                            el('span', null, task.title),
                            el('div', { className: 'ctvr-admin-toolbar' },
                                el('button', { type: 'button', className: 'button', onClick: () => toggleActive(task) }, task.is_active ? 'Desactivar' : 'Activar'),
                                el('button', { type: 'button', className: 'button button-link-delete', onClick: () => remove(task) }, 'Eliminar')
                            )
                        )))
                    ))
                ),
                el('div', { className: 'ctvr-card' },
                    el('h3', null, 'Checklist de salida'),
                    Object.entries(grouped.exit).map(([location, tasks]) => el('details', { key: location, open: true },
                        el('summary', null, areas[location] || location),
                        el('ul', null, tasks.map((task) => el('li', { key: task.id },
                            el('span', null, task.title),
                            el('div', { className: 'ctvr-admin-toolbar' },
                                el('button', { type: 'button', className: 'button', onClick: () => toggleActive(task) }, task.is_active ? 'Desactivar' : 'Activar'),
                                el('button', { type: 'button', className: 'button button-link-delete', onClick: () => remove(task) }, 'Eliminar')
                            )
                        )))
                    ))
                )
            )
        );
    }

    function renderApp(id, Component) {
        const container = document.getElementById(id);
        if (!container) {
            return;
        }
        const root = wp.element.createRoot ? wp.element.createRoot(container) : null;
        if (root) {
            root.render(el(Component));
        } else {
            wp.element.render(el(Component), container);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderApp('ctvr-dashboard-app', DashboardApp);
        renderApp('ctvr-availability-app', AvailabilityApp);
        renderApp('ctvr-requests-app', RequestsApp);
        renderApp('ctvr-reservations-app', ReservationsApp);
        renderApp('ctvr-checklist-app', ChecklistApp);
    });
})(window.wp);
