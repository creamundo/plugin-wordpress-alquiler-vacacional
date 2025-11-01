(function (wp) {
    if (!wp || !wp.element) {
        return;
    }

    const { createElement: el, useState, useEffect, useMemo } = wp.element;

    const WEEK_DAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];
    const STATUS_COLORS = {
        available: 'ctvr-day-available',
        blocked: 'ctvr-day-blocked',
        unpriced: 'ctvr-day-unpriced',
        selected: 'ctvr-day-selected',
        range: 'ctvr-day-range'
    };

    const NATIONALITIES = [
        'España', 'Francia', 'Alemania', 'Italia', 'Reino Unido', 'Portugal', 'Otros'
    ];

    const PROVINCES = [
        'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila', 'Badajoz', 'Barcelona', 'Burgos', 'Cáceres', 'Cádiz',
        'Cantabria', 'Castellón', 'Ciudad Real', 'Córdoba', 'Cuenca', 'Girona', 'Granada', 'Guadalajara', 'Guipúzcoa', 'Huelva',
        'Huesca', 'Islas Baleares', 'Jaén', 'La Coruña', 'La Rioja', 'Las Palmas', 'León', 'Lleida', 'Lugo', 'Madrid', 'Málaga',
        'Murcia', 'Navarra', 'Ourense', 'Palencia', 'Pontevedra', 'Salamanca', 'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Santa Cruz de Tenerife',
        'Teruel', 'Toledo', 'Valencia', 'Valladolid', 'Vizcaya', 'Zamora', 'Zaragoza'
    ];

    function formatMonthLabel(date) {
        const formatter = new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric' });
        return formatter.format(date);
    }

    function differenceInNights(start, end) {
        const startDate = new Date(start);
        const endDate = new Date(end);
        const diff = (endDate.getTime() - startDate.getTime()) / (1000 * 60 * 60 * 24);
        return Math.max(1, Math.round(diff));
    }

    function CalendarDay({ day, onSelect, isSelected, isInRange, isStart, isEnd }) {
        const classes = ['ctvr-day'];
        const stateClass = STATUS_COLORS[day.status] || STATUS_COLORS.unpriced;
        classes.push(stateClass);
        if (isSelected || isStart || isEnd) {
            classes.push(STATUS_COLORS.selected);
        } else if (isInRange) {
            classes.push(STATUS_COLORS.range);
        }

        const price = day.price !== null && day.price !== undefined ? Number(day.price).toFixed(2) : '';

        return el(
            'button',
            {
                type: 'button',
                className: classes.join(' '),
                onClick: () => onSelect(day),
                'aria-label': `${day.day} - ${day.status}`
            },
            el('span', { className: 'ctvr-day-number' }, new Date(day.day).getDate()),
            el('span', { className: 'ctvr-day-price' }, price)
        );
    }

    function CalendarGrid({ days, onSelect, selection }) {
        const firstDay = days.length ? new Date(days[0].day).getDay() : 0;
        const emptySlots = (firstDay + 6) % 7;
        const endSelected = selection.start && selection.end ? new Date(selection.end) : null;
        const startSelected = selection.start ? new Date(selection.start) : null;

        const cells = [];
        for (let i = 0; i < emptySlots; i++) {
            cells.push(el('div', { key: `empty-${i}`, className: 'ctvr-day empty' }));
        }

        days.forEach(day => {
            const dayDate = new Date(day.day);
            const isStart = selection.start && day.day === selection.start;
            const isEnd = selection.end && day.day === selection.end;
            let isInRange = false;
            if (startSelected && endSelected) {
                isInRange = dayDate > startSelected && dayDate < endSelected;
            }
            cells.push(
                el(CalendarDay, {
                    key: day.day,
                    day,
                    onSelect,
                    isSelected: isStart || isEnd,
                    isInRange,
                    isStart,
                    isEnd
                })
            );
        });

        return el('div', { className: 'ctvr-calendar-grid' }, cells);
    }

    function AgesFields({ count, ages, onChange }) {
        const fields = [];
        for (let i = 0; i < count; i++) {
            fields.push(
                el('div', { className: 'ctvr-field', key: `age-${i}` },
                    el('label', {}, `Edad persona ${i + 1}`),
                    el('input', {
                        type: 'number',
                        min: 0,
                        max: 120,
                        value: ages[i] || '',
                        onChange: (event) => onChange(i, event.target.value)
                    })
                )
            );
        }
        return el('div', { className: 'ctvr-age-fields' }, fields);
    }

    function Popin({ message, onClose }) {
        return el(
            'div',
            { className: 'ctvr-popin' },
            el('div', { className: 'ctvr-popin-content' },
                el('button', { type: 'button', className: 'ctvr-popin-close', onClick: onClose, 'aria-label': 'Cerrar' }, '×'),
                el('p', {}, message)
            )
        );
    }

    function FormSection({ selection, total, onSubmit, submitting, error, success }) {
        const [form, setForm] = useState({
            name: '',
            surname: '',
            phone: '',
            email: '',
            people: 1,
            ages: [],
            nationality: 'España',
            province: '',
            legal_text: '',
            accept_privacy: false,
            accept_news: false
        });

        useEffect(() => {
            const formNode = document.getElementById('ctvr-reservation-form');
            if (formNode) {
                formNode.scrollIntoView({ behavior: 'smooth' });
            }
        }, [selection.start, selection.end]);

        function updateField(field, value) {
            if (field === 'people') {
                const people = Math.max(1, Number(value) || 1);
                setForm(prev => ({
                    ...prev,
                    people,
                    ages: Array.from({ length: people }, (_, index) => prev.ages[index] || '')
                }));
                return;
            }

            setForm(prev => ({ ...prev, [field]: value }));
        }

        function updateAge(index, value) {
            setForm(prev => {
                const ages = prev.ages.slice();
                ages[index] = value;
                return { ...prev, ages };
            });
        }

        function handleSubmit(event) {
            event.preventDefault();
            onSubmit({
                ...form,
                people: Number(form.people),
                ages: form.ages.map(age => Number(age)),
                accept_privacy: !!form.accept_privacy,
                accept_news: !!form.accept_news
            });
        }

        const showProvinces = form.nationality === 'España';

        return el('form', { id: 'ctvr-reservation-form', className: 'ctvr-form', onSubmit: handleSubmit },
            el('p', { className: 'ctvr-form-info' }, 'El envío del formulario no compromete la reserva. Casa Trinidad confirmará en menos de 24h.'),
            el('div', { className: 'ctvr-form-grid' },
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Nombre'),
                    el('input', { type: 'text', value: form.name, required: true, onChange: (event) => updateField('name', event.target.value) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Apellidos'),
                    el('input', { type: 'text', value: form.surname, required: true, onChange: (event) => updateField('surname', event.target.value) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Teléfono'),
                    el('input', { type: 'tel', value: form.phone, required: true, onChange: (event) => updateField('phone', event.target.value) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Email'),
                    el('input', { type: 'email', value: form.email, required: true, onChange: (event) => updateField('email', event.target.value) })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Número de personas'),
                    el('input', {
                        type: 'number',
                        min: 1,
                        max: 12,
                        value: form.people,
                        required: true,
                        onChange: (event) => updateField('people', Number(event.target.value))
                    })
                ),
                el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Nacionalidad'),
                    el('select', {
                        value: form.nationality,
                        onChange: (event) => updateField('nationality', event.target.value)
                    },
                        NATIONALITIES.map(option => el('option', { key: option, value: option }, option))
                    )
                ),
                showProvinces && el('div', { className: 'ctvr-field' },
                    el('label', {}, 'Provincia'),
                    el('select', {
                        value: form.province,
                        required: true,
                        onChange: (event) => updateField('province', event.target.value)
                    },
                        el('option', { value: '' }, 'Selecciona provincia'),
                        PROVINCES.map(option => el('option', { key: option, value: option }, option))
                    )
                ),
                el('div', { className: 'ctvr-field ctvr-field-full' },
                    el('label', {}, 'Texto legal (primera capa)'),
                    el('textarea', {
                        value: form.legal_text,
                        rows: 4,
                        placeholder: 'Introduce aquí cualquier información legal o comentarios adicionales.',
                        onChange: (event) => updateField('legal_text', event.target.value)
                    })
                ),
                el('div', { className: 'ctvr-field ctvr-checkbox' },
                    el('label', {},
                        el('input', {
                            type: 'checkbox',
                            checked: form.accept_privacy,
                            required: true,
                            onChange: (event) => updateField('accept_privacy', event.target.checked)
                        }),
                        'Acepto la política de privacidad'
                    )
                ),
                el('div', { className: 'ctvr-field ctvr-checkbox' },
                    el('label', {},
                        el('input', {
                            type: 'checkbox',
                            checked: form.accept_news,
                            onChange: (event) => updateField('accept_news', event.target.checked)
                        }),
                        'Deseo recibir novedades de la zona'
                    )
                )
            ),
            el(AgesFields, { count: form.people, ages: form.ages, onChange: updateAge }),
            el('div', { className: 'ctvr-summary' },
                el('p', {}, `Fechas seleccionadas: ${selection.start} → ${selection.end}`),
                el('p', {}, `Noches: ${differenceInNights(selection.start, selection.end)}`),
                el('p', {}, `Importe estimado: ${total.toFixed(2)} €`)
            ),
            error && el('div', { className: 'ctvr-alert ctvr-alert-error' }, error),
            success && el('div', { className: 'ctvr-alert ctvr-alert-success' }, success),
            el('button', { type: 'submit', className: 'ctvr-submit', disabled: submitting }, submitting ? 'Enviando…' : 'Solicitar reserva')
        );
    }

    function CalendarApp() {
        const today = new Date();
        const [currentMonth, setCurrentMonth] = useState(new Date(today.getFullYear(), today.getMonth(), 1));
        const [days, setDays] = useState([]);
        const [loading, setLoading] = useState(false);
        const [selection, setSelection] = useState(() => {
            try {
                const stored = window.localStorage.getItem('ctvrSelection');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    return parsed;
                }
            } catch (error) {
                console.error(error);
            }
            return { start: null, end: null };
        });
        const [popin, setPopin] = useState(null);
        const [tooltip, setTooltip] = useState(false);
        const [total, setTotal] = useState(0);
        const [formState, setFormState] = useState({ submitting: false, error: '', success: '' });

        useEffect(() => {
            fetchMonth(currentMonth.getFullYear(), currentMonth.getMonth() + 1);
        }, [currentMonth]);

        useEffect(() => {
            if (selection.start && selection.end) {
                window.localStorage.setItem('ctvrSelection', JSON.stringify(selection));
                fetchTotal(selection.start, selection.end);
            }
        }, [selection.start, selection.end]);

        useEffect(() => {
            logEvent('calendar_view', { month: currentMonth.getMonth() + 1, year: currentMonth.getFullYear() });
        }, [currentMonth]);

        function fetchMonth(year, month) {
            setLoading(true);
            window.fetch(`${ctvrFront.restUrl}/calendar?year=${year}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    setDays(data.days || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }

        function fetchTotal(start, end) {
            window.fetch(`${ctvrFront.restUrl}/range-price?start=${start}&end=${end}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.valid) {
                        setTotal(Number(data.total));
                        setFormState(prev => ({ ...prev, error: '' }));
                    } else {
                        setPopin('El rango seleccionado no está disponible.');
                    }
                })
                .catch(() => setPopin('No se pudo calcular el precio.'));
        }

        function selectDay(day) {
            if (day.status === 'blocked') {
                setPopin('El día seleccionado está ocupado.');
                return;
            }

            if (day.price === null || day.price === undefined) {
                setPopin('El día seleccionado no tiene precio configurado.');
                return;
            }

            if (!selection.start || (selection.start && selection.end)) {
                setSelection({ start: day.day, end: null });
                setTotal(0);
                setTooltip(true);
                setFormState({ submitting: false, error: '', success: '' });
                return;
            }

            const startDate = new Date(selection.start);
            const endDate = new Date(day.day);

            if (endDate < startDate) {
                setSelection({ start: day.day, end: null });
                setTotal(0);
                setTooltip(true);
                return;
            }

            setSelection({ start: selection.start, end: day.day });
            setTooltip(false);
            logEvent('date_range_selected', { start: selection.start, end: day.day });
        }

        function goToNextMonth() {
            const next = new Date(currentMonth);
            next.setMonth(next.getMonth() + 1);
            setCurrentMonth(next);
        }

        function goToPrevMonth() {
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            if (currentMonth.getTime() <= monthStart.getTime()) {
                return;
            }

            const prev = new Date(currentMonth);
            prev.setMonth(prev.getMonth() - 1);
            if (prev.getTime() < monthStart.getTime()) {
                setCurrentMonth(monthStart);
            } else {
                setCurrentMonth(prev);
            }
        }

        const disablePrev = useMemo(() => {
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            return currentMonth.getTime() <= monthStart.getTime();
        }, [currentMonth, today]);

        function handleFormSubmit(data) {
            if (!selection.start || !selection.end) {
                setFormState(prev => ({ ...prev, error: 'Selecciona un rango de fechas.' }));
                return;
            }

            const nights = differenceInNights(selection.start, selection.end);
            if (nights < ctvrFront.minNights) {
                setPopin(`El número mínimo de noches es ${ctvrFront.minNights}.`);
                return;
            }

            setFormState({ submitting: true, error: '', success: '' });

            const payload = {
                start_date: selection.start,
                end_date: selection.end,
                nights,
                price_total: total,
                name: data.name,
                surname: data.surname,
                phone: data.phone,
                email: data.email,
                people: data.people,
                ages: data.ages,
                nationality: data.nationality,
                province: data.province,
                legal_text: data.legal_text,
                accept_privacy: data.accept_privacy,
                accept_news: data.accept_news
            };

            window.fetch(`${ctvrFront.restUrl}/request`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(async response => {
                    if (!response.ok) {
                        const body = await response.json().catch(() => ({}));
                        throw body;
                    }
                    return response.json();
                })
                .then(() => {
                    setFormState({ submitting: false, error: '', success: 'Solicitud enviada correctamente. Te contactaremos en menos de 24h.' });
                    logEvent('form_sent', { start: selection.start, end: selection.end });
                })
                .catch(error => {
                    const message = error && error.message ? error.message : 'No se pudo enviar la solicitud.';
                    const minNights = error && error.data && error.data.min_nights;
                    if (minNights) {
                        setPopin(`El número mínimo de noches es ${minNights}.`);
                    }
                    setFormState({ submitting: false, error: message, success: '' });
                });
        }

        function logEvent(event, payload = {}) {
            window.fetch(`${ctvrFront.restUrl}/event`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event, payload })
            }).catch(() => {});
        }

        return el('div', { className: 'ctvr-calendar-component' },
            el('div', { className: 'ctvr-calendar-header' },
                el('div', { className: 'ctvr-month-label' }, formatMonthLabel(currentMonth)),
                el('div', { className: 'ctvr-nav-buttons' },
                    el('button', { type: 'button', onClick: goToPrevMonth, disabled: disablePrev, className: 'ctvr-nav-button ctvr-nav-prev' }, '←'),
                    el('button', { type: 'button', onClick: goToNextMonth, className: 'ctvr-nav-button ctvr-nav-next' }, '→')
                )
            ),
            el('div', { className: 'ctvr-weekdays' }, WEEK_DAYS.map(label => el('span', { key: label }, label))),
            loading ? el('p', { className: 'ctvr-loading' }, 'Cargando…') : el(CalendarGrid, { days, onSelect: selectDay, selection }),
            tooltip && !selection.end && el('div', { className: 'ctvr-tooltip' }, 'Selecciona el día de salida'),
            el('div', { className: 'ctvr-legend' },
                el('span', { className: 'ctvr-legend-item ctvr-day-available' }, 'Libre con precio'),
                el('span', { className: 'ctvr-legend-item ctvr-day-blocked' }, 'Ocupado'),
                el('span', { className: 'ctvr-legend-item ctvr-day-unpriced' }, 'Sin precio')
            ),
            selection.start && selection.end && el('div', { className: 'ctvr-total' }, `Total estimado: ${total.toFixed(2)} €`),
            selection.start && selection.end && el(FormSection, {
                selection,
                total,
                onSubmit: handleFormSubmit,
                submitting: formState.submitting,
                error: formState.error,
                success: formState.success
            }),
            popin && el(Popin, { message: popin, onClose: () => setPopin(null) })
        );
    }

    function renderApp() {
        const container = document.getElementById('ctvr-calendar-app');
        if (!container) {
            return;
        }

        const root = wp.element.createRoot ? wp.element.createRoot(container) : null;
        if (root) {
            root.render(el(CalendarApp));
        } else {
            wp.element.render(el(CalendarApp), container);
        }
    }

    document.addEventListener('DOMContentLoaded', renderApp);
})(window.wp);
