(function (wp) {
    if (!wp || !wp.element) {
        return;
    }

    const { createElement: el, useState, useEffect } = wp.element;
    const cleaningSettings = window.ctvrCleaning || {};
    const REST_ROOT = (cleaningSettings.restUrl || '').replace(/\/$/, '');
    const TOKEN = cleaningSettings.token || '';

    function restFetch(method, body) {
        const options = { method: method || 'GET', headers: { 'Content-Type': 'application/json' } };
        if (body) {
            options.body = JSON.stringify(body);
        }
        return window.fetch(`${REST_ROOT}/workorder/${TOKEN}`, options).then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(data.message || 'Error de red');
                error.data = data;
                throw error;
            }
            return data;
        });
    }

    function CleaningApp() {
        const [state, setState] = useState({ loading: true, error: '', workorder: null, saving: false, notice: '', noticeType: '' });
        const [tab, setTab] = useState('entry');

        useEffect(() => {
            restFetch('GET')
                .then((data) => setState({ loading: false, error: '', workorder: data.workorder, saving: false, notice: '', noticeType: '' }))
                .catch((error) => setState({ loading: false, error: error.message || 'No se pudo cargar la reserva.', workorder: null, saving: false, notice: '', noticeType: '' }));
        }, []);

        if (state.loading) {
            return el('p', null, 'Cargando información de la reserva…');
        }
        if (state.error) {
            return el('p', { className: 'ctvr-alert ctvr-alert-error' }, state.error);
        }
        if (!state.workorder) {
            return el('p', null, 'No se encontró la reserva.');
        }

        const workorder = state.workorder;
        const payload = workorder.payload || {};
        const services = workorder.services || {};
        const settings = workorder.settings || {};

        function updateField(field, value) {
            setState((prev) => {
                const current = prev.workorder || {};
                const next = Object.assign({}, current, { [field]: value });
                return Object.assign({}, prev, { workorder: next, notice: '', noticeType: '' });
            });
        }

        function updateChecklist(scope, location, itemId, checked) {
            const key = scope === 'entry' ? 'entry_checklist' : 'exit_checklist';
            setState((prev) => {
                const current = prev.workorder || {};
                const list = Object.assign({}, current[key] || {});
                if (!list[location]) {
                    list[location] = {};
                }
                list[location][itemId] = checked;
                const next = Object.assign({}, current, { [key]: list });
                return Object.assign({}, prev, { workorder: next, notice: '', noticeType: '' });
            });
        }

        function updateServices(field, value) {
            setState((prev) => {
                const current = prev.workorder || {};
                const currentServices = current.services || {};
                const nextServices = Object.assign({}, currentServices, { [field]: value });
                const next = Object.assign({}, current, { services: nextServices });
                return Object.assign({}, prev, { workorder: next, notice: '', noticeType: '' });
            });
        }

        function updatePurchase(index, field, value) {
            setState((prev) => {
                const current = prev.workorder || {};
                const purchases = (current.purchases || []).slice();
                purchases[index] = Object.assign({}, purchases[index] || {}, { [field]: value });
                const next = Object.assign({}, current, { purchases });
                return Object.assign({}, prev, { workorder: next, notice: '', noticeType: '' });
            });
        }

        function addPurchase() {
            setState((prev) => {
                const current = prev.workorder || {};
                const purchases = (current.purchases || []).concat({ concept: '', amount: 0 });
                const next = Object.assign({}, current, { purchases });
                return Object.assign({}, prev, { workorder: next, notice: '', noticeType: '' });
            });
        }

        function removePurchase(index) {
            setState((prev) => {
                const current = prev.workorder || {};
                const purchases = (current.purchases || []).slice();
                purchases.splice(index, 1);
                const next = Object.assign({}, current, { purchases });
                return Object.assign({}, prev, { workorder: next, notice: '' });
            });
        }

        function save() {
            setState((prev) => Object.assign({}, prev, { saving: true, notice: '', noticeType: '' }));
            restFetch('POST', {
                entry_hours: workorder.entry_hours,
                exit_hours: workorder.exit_hours,
                entry_checklist: workorder.entry_checklist,
                exit_checklist: workorder.exit_checklist,
                services: workorder.services,
                purchases: workorder.purchases
            })
                .then(() => setState((prev) => Object.assign({}, prev, { saving: false, notice: 'Información guardada correctamente.', noticeType: 'success' })))
                .catch((error) => setState((prev) => Object.assign({}, prev, { saving: false, notice: error.message || 'Error al guardar.', noticeType: 'error' })));
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
            const groups = (workorder.checklists || {})[scope] || {};
            return el('div', { className: 'ctvr-form-grid' },
                Object.entries(groups).map(([location, items]) => el('fieldset', { key: `${scope}-${location}` },
                    el('legend', null, areaLabels[location] || location),
                    items.map((item) => {
                        const completed = Boolean(workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'] && workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'][location] && workorder[scope === 'entry' ? 'entry_checklist' : 'exit_checklist'][location][item.id]);
                        return el('label', { key: item.id, className: 'ctvr-field ctvr-checkbox' },
                            el('input', {
                                type: 'checkbox',
                                checked: completed,
                                onChange: (event) => updateChecklist(scope, location, item.id, event.target.checked)
                            }),
                            item.title
                        );
                    })
                ))
            );
        }

        const purchases = workorder.purchases || [];
        const cleaningHourPrice = Number(settings.cleaning_hour_price || 0);
        const entryHours = Number(workorder.entry_hours || 0);
        const exitHours = Number(workorder.exit_hours || 0);
        const entryCost = entryHours * cleaningHourPrice;
        const exitCost = exitHours * cleaningHourPrice;
        const keyCost = services.key_delivery ? Number(settings.key_delivery_price || 0) : 0;
        const linenCost = services.linen ? Number(settings.linen_cleaning_price || 0) : 0;
        const purchasesCost = purchases.reduce((sum, purchase) => sum + Number(purchase.amount || 0), 0);
        const platformFee = (() => {
            if (!services.platform || !settings.platforms) {
                return 0;
            }
            try {
                const parsed = JSON.parse(settings.platforms);
                const match = parsed.find((item) => item.name === services.platform);
                if (!match) {
                    return 0;
                }
                return Number(workorder.price_total || 0) * (Number(match.percentage || 0) / 100);
            } catch (error) {
                return 0;
            }
        })();
        const taxHold = Number(workorder.price_total || 0) * (Number(settings.tax_percentage || 0) / 100);
        const subtotal = entryCost + exitCost + keyCost + linenCost + purchasesCost;
        const managementBase = Math.max(0, Number(workorder.price_total || 0) - platformFee - taxHold - subtotal);
        const managementFee = Number(settings.management_percentage || 0) ? managementBase * (Number(settings.management_percentage) / 100) : 0;
        const totalDue = subtotal + managementFee;

        return el('div', null,
            el('header', { style: { marginBottom: '1rem' } },
                el('h2', null, `Reserva ${workorder.start_date} → ${workorder.end_date}`),
                el('p', null, `${payload.name || ''} ${payload.surname || ''} · ${payload.nationality || ''}`),
                el('p', null, `${payload.people || 0} personas · ${Array.isArray(payload.ages) ? payload.ages.join(', ') : ''}`)
            ),
            el('nav', { className: 'ctvr-toolbar' },
                el('button', { type: 'button', className: `ctvr-toolbar-button ${tab === 'entry' ? 'ctvr-tab-active' : ''}`, onClick: () => setTab('entry') }, 'Checklist entrada'),
                el('button', { type: 'button', className: `ctvr-toolbar-button ${tab === 'exit' ? 'ctvr-tab-active' : ''}`, onClick: () => setTab('exit') }, 'Checklist salida'),
                el('button', { type: 'button', className: `ctvr-toolbar-button ${tab === 'services' ? 'ctvr-tab-active' : ''}`, onClick: () => setTab('services') }, 'Servicios y gastos')
            ),
            tab === 'entry' && renderChecklist('entry'),
            tab === 'exit' && renderChecklist('exit'),
            tab === 'services' && el('div', { className: 'ctvr-form-grid' },
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Horas limpieza entrada'),
                    el('input', { type: 'number', min: 0, step: '0.5', value: workorder.entry_hours || '', onChange: (event) => updateField('entry_hours', event.target.value) }),
                    el('small', null, `Precio hora: ${cleaningHourPrice.toFixed(2)} €`)
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Horas limpieza salida'),
                    el('input', { type: 'number', min: 0, step: '0.5', value: workorder.exit_hours || '', onChange: (event) => updateField('exit_hours', event.target.value) })
                ),
                el('div', { className: 'ctvr-field ctvr-checkbox' },
                    el('label', null,
                        el('input', { type: 'checkbox', checked: Boolean(services.key_delivery), onChange: (event) => updateServices('key_delivery', event.target.checked) }),
                        `Entrega de llaves (+${Number(settings.key_delivery_price || 0).toFixed(2)} €)`
                    )
                ),
                el('div', { className: 'ctvr-field ctvr-checkbox' },
                    el('label', null,
                        el('input', { type: 'checkbox', checked: Boolean(services.linen), onChange: (event) => updateServices('linen', event.target.checked) }),
                        `Gestión ropa de cama (+${Number(settings.linen_cleaning_price || 0).toFixed(2)} €)`
                    )
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', null, 'Plataforma de la reserva'),
                    el('input', { type: 'text', value: services.platform || '', onChange: (event) => updateServices('platform', event.target.value) })
                ),
                el('div', { className: 'ctvr-field ctvr-field-full' },
                    el('label', null, 'Compras intermedias'),
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
                            el('button', { type: 'button', className: 'ctvr-inline-button', onClick: () => removePurchase(index) }, 'Eliminar')
                        )
                    )),
                    el('button', { type: 'button', className: 'ctvr-toolbar-button', onClick: addPurchase }, 'Añadir compra')
                )
            ),
            el('div', { className: 'ctvr-summary' },
                el('strong', null, 'Resumen'),
                el('span', null, `Horas entrada (${entryHours}h): ${entryCost.toFixed(2)} €`),
                el('span', null, `Horas salida (${exitHours}h): ${exitCost.toFixed(2)} €`),
                el('span', null, `Entrega de llaves: ${keyCost.toFixed(2)} €`),
                el('span', null, `Ropa de cama: ${linenCost.toFixed(2)} €`),
                el('span', null, `Compras: ${purchasesCost.toFixed(2)} €`),
                platformFee ? el('span', null, `Comisión plataforma: ${platformFee.toFixed(2)} €`) : null,
                taxHold ? el('span', null, `Retención impuestos: ${taxHold.toFixed(2)} €`) : null,
                managementFee ? el('span', null, `Gestión: ${managementFee.toFixed(2)} €`) : null,
                el('strong', null, `Total a cobrar: ${totalDue.toFixed(2)} €`)
            ),
            state.notice && el('p', { className: state.noticeType === 'error' ? 'ctvr-alert ctvr-alert-error' : 'ctvr-alert ctvr-alert-success' }, state.notice),
            el('button', { type: 'button', className: 'ctvr-submit', disabled: state.saving, onClick: save }, state.saving ? 'Guardando…' : 'Guardar información')
        );
    }

    function renderApp() {
        const container = document.getElementById('ctvr-cleaning-app');
        if (!container) {
            return;
        }
        const root = wp.element.createRoot ? wp.element.createRoot(container) : null;
        if (root) {
            root.render(el(CleaningApp));
        } else {
            wp.element.render(el(CleaningApp), container);
        }
    }

    document.addEventListener('DOMContentLoaded', renderApp);
})(window.wp);
