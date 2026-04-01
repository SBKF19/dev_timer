import tippy from 'tippy.js';
import 'tippy.js/dist/tippy.css';

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    // Récupération des données depuis le HTML (dataset)
    const settings = calendarEl.dataset;
    const businessHours = JSON.parse(settings.businessHours);

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'fr',
        firstDay: 1,
        weekends: false,
        allDaySlot: false,
        height: 'auto',
        expandRows: false,
        slotMinTime: settings.minTime, 
        slotMaxTime: settings.maxTime,
        businessHours: businessHours, 
        selectConstraint: 'businessHours',
        selectable: true,
        events: settings.eventsUrl,

        events: settings.eventsUrl,

        eventDidMount: function(info) {
            const comm = info.event.extendedProps.commentary;
            if (comm && comm.trim() !== "") {
                tippy(info.el, {
                    content: comm,
                    placement: 'top',
                    appendTo: () => document.body,
                    onShow(instance) {
                        instance.popper.querySelector('.tippy-box').classList.add('bg-neutral', 'text-neutral-content', 'rounded-lg', 'shadow-lg', 'p-2', 'text-sm');
                    }
                });
            }
        },

        selectOverlap: (event) => !event.id.startsWith('holiday_'),
        
        datesSet: (info) => updateStats(info.startStr, info.endStr, settings.statsUrl),

        select: function(info) {
            window.location.href = `${settings.addUrl}?start=${info.startStr}&end=${info.endStr}`;
        },

        eventClick: function(info) {
            if (info.event.id.startsWith('holiday_')) return;
            const numericId = info.event.id.replace('entry_', '');
            window.location.href = settings.editUrl.replace('PLACEHOLDER', numericId);
        }
    });

    calendar.render();
});

function updateStats(start, end, baseUrl) {
    fetch(`${baseUrl}?start=${start}&end=${end}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('stat-saisie').innerText = data.saisie;
            document.getElementById('stat-restant').innerText = data.restant;
        })
        .catch(err => console.error('Erreur stats:', err));
}